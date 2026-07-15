<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Checkout;

use Livewire\Component;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Classes\Countries;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Services\Payments\PaymentTransactionStarter;
use Dashed\DashedCore\Classes\Caching\IdentifiedVisitor;

class ProformaCheckout extends Component
{
    public string $orderHash = '';
    public ?Order $order = null;

    // Contactgegevens
    public string $email = '';
    public string $password = '';
    public string $passwordConfirmation = '';
    public bool $marketing = false;

    // Bezorgadres (zelfde namen als Checkout)
    public string $firstName = '';
    public string $lastName = '';
    public string $phoneNumber = '';
    public string $street = '';
    public string $houseNr = '';
    public string $zipCode = '';
    public string $city = '';
    public string $country = 'Nederland';
    public array $countryList = [];

    // Bedrijf
    public bool $isCompany = false;
    public string $company = '';
    public string $taxId = '';

    // Afwijkend factuuradres
    public bool $invoiceAddress = false;
    public string $invoiceStreet = '';
    public string $invoiceHouseNr = '';
    public string $invoiceZipCode = '';
    public string $invoiceCity = '';
    public string $invoiceCountry = '';

    // Notitie + voorwaarden
    public string $note = '';
    public bool $generalCondition = false;

    // Config-vlaggen (identiek aan Checkout::mount())
    public int $accountRequired = 2;
    public int $firstAndLastnameRequired = 0;
    public int $companyRequired = 2;
    public int $phoneNumberRequired = 0;
    public int $useDeliveryAddressAsInvoiceAddress = 1;

    // Verzending (cartless)
    public bool $shippingEnabled = false;
    public array $shippingMethods = [];
    public ?string $shippingMethod = null;

    // Betaalmethodes (echte DB-methodes, net als Checkout)
    public $paymentMethods = [];
    public $paymentMethod;

    public function mount(string $orderHash): void
    {
        $this->order = Order::where('hash', $orderHash)
            ->where('is_proforma', true)
            ->firstOrFail();

        $this->orderHash = $orderHash;
        $this->email = (string) $this->order->email;
        $this->firstName = (string) $this->order->first_name;
        $this->lastName = (string) $this->order->last_name;
        $this->phoneNumber = (string) $this->order->phone_number;
        $this->street = (string) $this->order->street;
        $this->houseNr = (string) $this->order->house_nr;
        $this->zipCode = (string) $this->order->zip_code;
        $this->city = (string) $this->order->city;
        $this->country = filled($this->order->country) ? $this->order->country : 'Nederland';
        $this->company = (string) $this->order->company_name;
        $this->taxId = (string) $this->order->btw_id;
        $this->note = (string) $this->order->note;
        $this->marketing = (bool) $this->order->marketing;

        $this->invoiceStreet = (string) $this->order->invoice_street;
        $this->invoiceHouseNr = (string) $this->order->invoice_house_nr;
        $this->invoiceZipCode = (string) $this->order->invoice_zip_code;
        $this->invoiceCity = (string) $this->order->invoice_city;
        $this->invoiceCountry = (string) $this->order->invoice_country;

        $this->countryList = Countries::getAllSelectedCountries();

        // Config-vlaggen exact zoals Checkout::mount() (1 = verplicht, 2 = optioneel/toggle, 0 = verborgen).
        $this->accountRequired = Customsetting::get('checkout_account', default: 2);
        $this->firstAndLastnameRequired = Customsetting::get('checkout_form_name', default: 0);
        $this->companyRequired = Customsetting::get('checkout_form_company_name', default: 2);
        $this->phoneNumberRequired = Customsetting::get('checkout_form_phone_number_delivery_address', default: 0);
        $this->useDeliveryAddressAsInvoiceAddress = Customsetting::get('checkout_delivery_address_standard_invoice_address', default: 1) ?: 0;

        // Toggle-defaults spiegelen Checkout, maar op basis van de order (geen sessie-user).
        if (filled($this->order->invoice_street)) {
            $this->invoiceAddress = true;
        } else {
            $this->invoiceAddress = Customsetting::get('checkout_delivery_address_standard_invoice_address')
                ? false
                : true;
        }

        if (filled($this->order->company_name)) {
            $this->isCompany = true;
        } else {
            $this->isCompany = $this->companyRequired == 1;
        }

        // Heeft de kassa zelf al een verzendmethode vastgelegd, dan staan de kosten
        // al in het proforma-totaal (als losse regel) en mag/hoeft de klant niets te
        // kiezen. De verzendkeuze verschijnt alleen als er nog geen verzending ligt,
        // zodat submit() de kosten nooit dubbel optelt.
        $posShippingAlreadySet = filled($this->order->shipping_method_id);
        $this->shippingEnabled = (bool) $this->order->proforma_allow_shipping && ! $posShippingAlreadySet;

        $this->retrievePaymentMethods();

        if ($this->shippingEnabled) {
            $this->retrieveShippingMethods();
        }
    }

    public function rules(): array
    {
        // Spiegelt Checkout::rules(). De proforma heeft geen postpay/custom fields, dus
        // die condities vervallen. De verzendregel blijft versoepeld: alleen verplicht
        // wanneer verzending aanstaat en er daadwerkelijk methodes zijn.
        return [
            'firstName' => [
                'max:255',
                Rule::requiredIf($this->firstAndLastnameRequired == 1),
            ],
            'lastName' => ['required', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => [
                Rule::requiredIf(Customsetting::get('checkout_account') == 'required' && ! Auth::check()),
                'nullable',
                'min:6',
                'max:255',
            ],
            'passwordConfirmation' => ['same:password'],
            'street' => ['required', 'max:255'],
            'houseNr' => ['required', 'max:255'],
            'zipCode' => ['required', 'max:10'],
            'city' => ['required', 'max:255'],
            'country' => ['required', 'max:255'],
            'phoneNumber' => [
                Rule::requiredIf($this->phoneNumberRequired == 1),
                'max:255',
            ],
            'company' => [
                Rule::requiredIf($this->companyRequired == 1),
                'max:255',
            ],
            'taxId' => ['max:255'],
            'note' => ['max:1500'],
            'invoiceStreet' => ['max:255'],
            'invoiceHouseNr' => [
                Rule::requiredIf((bool) $this->invoiceStreet),
                'max:255',
            ],
            'invoiceZipCode' => [
                Rule::requiredIf((bool) $this->invoiceStreet),
                'max:255',
            ],
            'invoiceCity' => [
                Rule::requiredIf((bool) $this->invoiceStreet),
                'max:255',
            ],
            'invoiceCountry' => [
                Rule::requiredIf((bool) $this->invoiceStreet),
                'max:255',
            ],
            'shippingMethod' => [$this->shippingEnabled && count($this->shippingMethods) > 0 ? 'required' : 'nullable', 'max:255'],
            'paymentMethod' => ['required', 'max:255'],
        ];
    }

    public function retrievePaymentMethods(): void
    {
        // Een proforma heeft GEEN sessie-cart. getAvailablePaymentMethods() zou de
        // available_from_amount-filter dus tegen een lege cart-total (0) draaien.
        // We geven daarom de proforma-total expliciet mee als total-override, zodat
        // de filtering cartless klopt. De country/zone-filter blijft identiek.
        $proformaTotal = (float) ($this->order->total ?? 0);

        $this->paymentMethods = $this->country
            ? ShoppingCart::getAvailablePaymentMethods($this->country, null, $proformaTotal)
            : [];

        if (
            Customsetting::get('first_payment_method_selected', null, true)
            && (
                ! $this->paymentMethod
                || ! in_array($this->paymentMethod, collect($this->paymentMethods)->pluck('id')->toArray())
            )
            && count($this->paymentMethods)
        ) {
            $this->paymentMethod = collect($this->paymentMethods)->first()['id'] ?? '';
        }
    }

    public function retrieveShippingMethods(): void
    {
        // Een proforma heeft GEEN sessie-cart (maatwerkproducten mogen daar nooit
        // in komen). De min/max-order-value filtering moet daarom tegen de eigen
        // proforma-total draaien, niet tegen een lege cart. We geven die total
        // expliciet mee via $orderTotalOverride zodat ShoppingCart cartless werkt.
        $proformaTotal = (float) ($this->order->total ?? 0);

        $methods = $this->country
            ? ShoppingCart::getAvailableShippingMethods($this->country, '', null, $proformaTotal)
            : [];

        // getAvailableShippingMethods geeft een Collection terug wanneer een zone
        // matcht en een lege array wanneer niet; collect() normaliseert beide.
        $this->shippingMethods = collect($methods)->values()->toArray();

        if (! $this->shippingMethod && count($this->shippingMethods)) {
            $this->shippingMethod = (string) ($this->shippingMethods[0]['id'] ?? '');
        }
    }

    public function updatedCountry(): void
    {
        $this->retrievePaymentMethods();

        if ($this->shippingEnabled) {
            $this->retrieveShippingMethods();
        }
    }

    public function submit()
    {
        $this->validate();

        $order = $this->order;
        $order->refresh();

        // Guard: already paid - no mutation, redirect back to the controller
        // which will serve the "already paid" view.
        if ($order->isPaidFor()) {
            return redirect()->route('dashed.frontend.proforma-checkout', ['orderHash' => $order->hash]);
        }

        // Betaalmethode oplossen tegen de proforma-total (cartless), net als Checkout::submit()
        // dat tegen ShoppingCart::getPaymentMethods() doet.
        $paymentMethods = ShoppingCart::getPaymentMethods('online', (float) $order->total, null, false);
        $paymentMethod = null;
        foreach ($paymentMethods as $thisPaymentMethod) {
            if ((string) $thisPaymentMethod['id'] === (string) $this->paymentMethod) {
                $paymentMethod = $thisPaymentMethod;
            }
        }

        // Mutation block: runs ONLY on the first submit while the order is still
        // a concept. A re-submit after a payment abandonment (order=pending) skips
        // this entirely, so totals and shipping lines are never compounded.
        if ($order->isConcept()) {
            // Optioneel account aanmaken, exact zoals Checkout::submit().
            if (Customsetting::get('checkout_account') != 'disabled' && Auth::guest() && $this->password) {
                if (! User::where('email', $this->email)->count()) {
                    $user = new User();
                    $user->first_name = $this->firstName;
                    $user->last_name = $this->lastName;
                    $user->email = $this->email;
                    $user->password = Hash::make($this->password);
                    $user->save();

                    Auth::login($user, 1);
                    IdentifiedVisitor::mark();
                }
            }

            $order->first_name = $this->firstName;
            $order->last_name = $this->lastName;
            $order->email = $this->email;
            $order->phone_number = $this->phoneNumber;
            $order->street = $this->street;
            $order->house_nr = $this->houseNr;
            $order->zip_code = $this->zipCode;
            $order->city = $this->city;
            $order->country = $this->country;
            $order->marketing = $this->marketing;
            $order->company_name = $this->company;
            $order->btw_id = $this->taxId;
            $order->note = $this->note;
            $order->invoice_street = $this->invoiceStreet;
            $order->invoice_house_nr = $this->invoiceHouseNr;
            $order->invoice_zip_code = $this->invoiceZipCode;
            $order->invoice_city = $this->invoiceCity;
            $order->invoice_country = $this->invoiceCountry;
            $order->invoice_id = 'PROFORMA';
            $order->status = 'pending';

            if (Auth::check()) {
                $order->user_id = Auth::user()->id;
            }

            if ($paymentMethod) {
                $order->payment_method_id = $paymentMethod['id'];
            }

            $selectedShipping = null;
            if ($this->shippingEnabled && $this->shippingMethod) {
                foreach ($this->shippingMethods as $method) {
                    if ((string) ($method['id'] ?? '') === (string) $this->shippingMethod) {
                        $selectedShipping = $method;

                        break;
                    }
                }

                if ($selectedShipping) {
                    $order->shipping_method_id = $selectedShipping['id'];
                }
            }

            // Verzendkosten in het ordertotaal verrekenen VOOR het opslaan en voor de
            // betaling. outstandingAmount() = max(0, total - paid), dus zonder deze
            // ophoging zou de klant de verzendkosten niet betalen. We spiegelen de
            // manier waarop ConceptOrderService::saveAsConcept de bedragen zet: de
            // (btw-inclusieve) prijs telt op bij total en subtotal, het btw-deel bij btw.
            $shippingCost = 0.0;
            $shippingVatRate = 21.0;
            if ($selectedShipping && ($selectedShipping['costs'] ?? 0) > 0) {
                $shippingCost = (float) $selectedShipping['costs'];
                $shippingVatRate = (float) ($selectedShipping['vat_rate'] ?? 21);

                $shippingVat = $shippingVatRate > 0
                    ? $shippingCost - ($shippingCost / (1 + $shippingVatRate / 100))
                    : 0.0;

                $order->total = (float) $order->total + $shippingCost;
                $order->subtotal = (float) $order->subtotal + $shippingCost;
                $order->btw = round((float) $order->btw + $shippingVat, 2);
            }

            $order->save();
            $order->refresh();

            // Verzendkosten als losse regel (product_id = null), net als Checkout::submit().
            if ($shippingCost > 0) {
                $orderProduct = new OrderProduct();
                $orderProduct->quantity = 1;
                $orderProduct->product_id = null;
                $orderProduct->order_id = $order->id;
                $orderProduct->name = $selectedShipping['correctName'] ?? ($selectedShipping['name'] ?? 'Verzendkosten');
                $orderProduct->price = $shippingCost;
                $orderProduct->vat_rate = $shippingVatRate;
                $orderProduct->discount = 0;
                $orderProduct->product_extras = [];
                $orderProduct->sku = 'shipping_costs';
                $orderProduct->save();
            }

            $order->createInvoice();
            $order->refresh();
        }

        // Betaling starten. De methode-keuze spiegelt Checkout::submit() (regels 1126-1172):
        // een echte betaalmethode bepaalt de PSP; valt terug op de eerste verbonden PSP.
        // De proforma houdt de remainder-context aan (restbetaling), niet de checkout-context.
        $orderPayment = new OrderPayment();
        $orderPayment->order_id = $order->id;
        $orderPayment->amount = $order->outstandingAmount();
        $orderPayment->status = 'pending';
        $orderPayment->hash = (string) Str::uuid();

        $psp = null;
        if ($paymentMethod) {
            $psp = $paymentMethod['psp'];
        } else {
            foreach (ecommerce()->builder('paymentServiceProviders') as $pspId => $ecommercePSP) {
                if ($ecommercePSP['class']::isConnected()) {
                    $psp = $pspId;
                }
            }
        }

        if (! $psp) {
            session()->flash('error', 'Er is geen betaalprovider beschikbaar.');

            return;
        }

        $orderPayment->psp = $psp;

        if (! $paymentMethod) {
            $orderPayment->payment_method = $psp;
        } elseif ($orderPayment->psp == 'own') {
            // Handmatige betaalmethode (bankoverschrijving/contant): geen PSP-redirect.
            $orderPayment->payment_method_id = $paymentMethod['id'];
            $orderPayment->amount = 0;
            $orderPayment->status = 'paid';
        } else {
            $orderPayment->payment_method = $paymentMethod['name'];
            $orderPayment->payment_method_id = $paymentMethod['id'];
        }

        $orderPayment->save();
        $orderPayment->refresh();
        // Koppel de al-geladen order expliciet zodat de PSP (o.a. PayNL leest
        // $orderPayment->order->orderProducts) niet afhankelijk is van een
        // lazy-load die in sommige contexten null teruggeeft.
        $orderPayment->setRelation('order', $order);

        // Handmatige (own) betaling: geen PSP-transactie, direct naar wachten-op-bevestiging.
        if ($orderPayment->psp == 'own' && $orderPayment->status == 'paid') {
            $order->changeStatus('waiting_for_confirmation');

            return redirect(url(ShoppingCart::getCompleteUrl()).'?paymentId='.$orderPayment->hash);
        }

        try {
            $transaction = PaymentTransactionStarter::start($orderPayment, PaymentTransactionStarter::CONTEXT_REMAINDER_PAYMENT);
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', 'De betaling kon niet gestart worden.');

            return;
        }

        if (! empty($transaction['redirectUrl'])) {
            return redirect()->away($transaction['redirectUrl']);
        }

        session()->flash('error', 'De betaling kon niet gestart worden.');
    }

    public function render()
    {
        return view('dashed-ecommerce-core::livewire.frontend.checkout.proforma-checkout');
    }
}
