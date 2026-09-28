<?php

namespace Dashed\DashedEcommerceCore\Services\Quotes;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Quote;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedCore\Classes\AdminActionMonitor;
use Dashed\DashedEcommerceCore\Classes\ManualPaymentPin;
use Dashed\DashedEcommerceCore\Mail\ProformaCheckoutMail;
use Dashed\DashedEcommerceCore\Events\Quotes\QuoteConverted;
use Dashed\DashedEcommerceCore\Services\OnAccount\OnAccountOverride;
use Dashed\DashedEcommerceCore\Services\OnAccount\OnAccountOrderPlacer;

/**
 * De enige deur van een geaccepteerde offerte naar een bestelling. De knop
 * van de klant, de actie in het CMS en een latere API lopen hier doorheen.
 *
 * Een lock per offerte plus een verse lees van order_id binnen de lock: een
 * dubbelklik of een ververste POST levert dezelfde order op. De unieke index
 * op dashed__orders.quote_id is de databasebackstop.
 */
class QuoteConverter
{
    public const CHECKOUT = 'checkout';
    public const PAYMENT_LINK = 'payment_link';
    public const ON_ACCOUNT = 'on_account';
    public const DRAFT = 'draft';
    public const PAID = 'paid';

    public const MODES = [self::CHECKOUT, self::PAYMENT_LINK, self::ON_ACCOUNT, self::DRAFT, self::PAID];

    /**
     * @param  array{override?: bool, by_admin?: bool, pin?: ?string}  $options
     */
    public static function convert(Quote $quote, string $mode, array $options = []): QuoteConversionResult
    {
        if (! in_array($mode, self::MODES, true)) {
            throw new \InvalidArgumentException('Onbekende omzetmodus '.$mode);
        }

        // Voor het bouwen, zodat een foute pincode geen order achterlaat.
        // Zonder order telt de rate limiter per gebruiker of IP-adres en komt
        // er geen notitie bij een bestelling; er is er dan nog geen.
        if ($mode === self::PAID) {
            ManualPaymentPin::verifyOrFail($options['pin'] ?? null);
        }

        return Cache::lock('quote-convert:'.$quote->id, 10)->block(5, function () use ($quote, $mode, $options) {
            $quote = $quote->fresh(['lines', 'order', 'user']);

            if ($quote->order) {
                // Een order die nog concept is en geen proforma, is nooit
                // geplaatst: een geweigerde omzetting op rekening, of een
                // plaatsing die halverwege klapte. Dat is geen succes.
                if ($quote->order->status === 'concept' && ! $quote->order->is_proforma) {
                    return new QuoteConversionResult($quote->order, null, 'not_placed');
                }

                return new QuoteConversionResult($quote->order, self::redirectFor($quote->order));
            }

            if ($quote->status !== Quote::STATUS_ACCEPTED) {
                return new QuoteConversionResult(refusal: 'not_accepted');
            }

            $byAdmin = (bool) ($options['by_admin'] ?? false);

            // Zonder e-mailadres kan er geen betaallink verstuurd worden; dat
            // vooraf weigeren, zonder order, net als bij on_account.
            if ($mode === self::PAYMENT_LINK && ! $quote->email) {
                return new QuoteConversionResult(refusal: 'no_email');
            }

            // Een beheerder krijgt een weigering vooraf, zonder order: hij kan
            // een andere modus kiezen of doorzetten. NOT_ENABLED (geen methode
            // op rekening gekoppeld) weigert refusalBeforeCreate() altijd.
            if ($mode === self::ON_ACCOUNT && $byAdmin) {
                if (! $quote->user) {
                    return new QuoteConversionResult(refusal: 'no_customer');
                }

                $refusal = OnAccountOverride::refusalBeforeCreate(
                    $quote->user,
                    QuoteTotals::for($quote)->total,
                    (string) $quote->site_id,
                    (bool) ($options['override'] ?? false),
                );

                if ($refusal) {
                    return new QuoteConversionResult(refusal: (string) $refusal->reason);
                }
            }

            $originalLocale = App::getLocale();
            App::setLocale($quote->locale);

            try {
                // Bouwen en koppelen in een transactie: build() slaat de order
                // (met quote_id) op voordat de regels erin staan. Klapt het
                // daartussen, dan bleef een order achter die de unieke index op
                // quote_id bezet hield, en liep elke nieuwe poging daarop vast.
                // Het plaatsen blijft erbuiten, want dat verstuurt mails en jobs.
                $order = DB::transaction(function () use ($quote) {
                    $order = QuoteToOrder::build($quote);
                    $quote->order_id = $order->id;
                    $quote->save();

                    return $order;
                });

                $result = match ($mode) {
                    self::CHECKOUT, self::DRAFT => self::asProforma($order),
                    self::PAYMENT_LINK => self::asPaymentLink($order),
                    self::ON_ACCOUNT => $byAdmin
                        ? self::onAccountByAdmin($order, $quote, (bool) ($options['override'] ?? false))
                        : self::onAccountByCustomer($order, $quote),
                    self::PAID => self::asPaid($order),
                };
            } finally {
                App::setLocale($originalLocale);
            }

            // Alleen vuren bij een omzetting die echt gelukt is: een geweigerd
            // op-rekening-verzoek laat een concept achter dat niet geplaatst
            // is, en dat is geen omzetting om op te reageren (geen mail, geen
            // webhook).
            if ($result->ok()) {
                QuoteConverted::dispatch($quote, $result->order, $mode);
            }

            return $result;
        });
    }

    /**
     * Waar de klant heen moet voor deze order, of null als er niets te
     * betalen is. Zelfde voorwaarde als ProformaCheckoutController: een order
     * die al wacht op bevestiging (overboeking) of deels betaald is, telt
     * ook als betaald voor deze knop, anders belandt de klant op de
     * "al betaald"-pagina van de proforma-checkout.
     */
    public static function redirectFor(Order $order): ?string
    {
        if ($order->is_proforma && ! $order->isPaidFor()) {
            return route('dashed.frontend.proforma-checkout', ['orderHash' => $order->hash]);
        }

        return null;
    }

    private static function asProforma(Order $order): QuoteConversionResult
    {
        $order->is_proforma = true;
        $order->proforma_allow_shipping = true;
        $order->proforma_sent_at = now();
        $order->invoice_id = null;
        $order->save();

        $order = $order->fresh();

        return new QuoteConversionResult($order, self::redirectFor($order));
    }

    private static function asPaymentLink(Order $order): QuoteConversionResult
    {
        $result = self::asProforma($order);

        rescue(fn () => Mail::to($result->order->email)->send(new ProformaCheckoutMail($result->order, (string) $result->redirectUrl)));

        return $result;
    }

    /**
     * Zegt de kredietcontrole nee, dan blijft de order concept en gaat er een
     * melding uit. De klant heeft ja gezegd en dat staat vast; een limiet die
     * op dat ene moment omvalt hoort geen deal te laten verdampen.
     */
    private static function onAccountByCustomer(Order $order, Quote $quote): QuoteConversionResult
    {
        $customer = $quote->user;

        if (! $customer) {
            self::refuse($order, $quote, 'no_customer');

            return new QuoteConversionResult($order->fresh(), refusal: 'no_customer');
        }

        $check = OnAccountOverride::precheck($customer, (float) $order->total, (string) $order->site_id);

        if (! $check->allowed) {
            self::refuse($order, $quote, (string) $check->reason);

            return new QuoteConversionResult($order->fresh(), refusal: (string) $check->reason);
        }

        $payment = $order->orderPayments()->create([
            'psp' => 'own',
            'payment_method' => __('Op rekening'),
            'amount' => 0,
            'status' => 'pending',
        ]);

        OnAccountOrderPlacer::place($order, $payment, $customer);

        return new QuoteConversionResult($order->fresh());
    }

    /** De controle vooraf gebeurde al in convert(); dit plaatst, met override. */
    private static function onAccountByAdmin(Order $order, Quote $quote, bool $override): QuoteConversionResult
    {
        $check = OnAccountOverride::placeByAdmin($order, $quote->user, $override);

        if (! $check->allowed) {
            self::refuse($order, $quote, (string) $check->reason);

            return new QuoteConversionResult($order->fresh(), refusal: (string) $check->reason);
        }

        return new QuoteConversionResult($order->fresh());
    }

    private static function asPaid(Order $order): QuoteConversionResult
    {
        $payment = $order->orderPayments()->create([
            'psp' => 'own',
            'payment_method' => 'manual_payment',
            'amount' => $order->total,
        ]);

        $order->changeStatus($payment->changeStatus('paid'));

        return new QuoteConversionResult($order->fresh());
    }

    private static function refuse(Order $order, Quote $quote, string $reason): void
    {
        OrderLog::createLog(orderId: $order->id, tag: 'order.quote-on-account-refused', note: $reason);

        rescue(fn () => AdminActionMonitor::alert(__('Offerte geaccepteerd, maar niet op rekening te zetten'), [
            __('Offerte') => $quote->displayNumber(),
            __('Bestelling') => (string) $order->id,
            __('Klant') => (string) $quote->email,
            __('Reden') => $reason,
        ]));
    }
}
