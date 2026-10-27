<?php

namespace Dashed\DashedEcommerceCore\Services\Quotes;

use RuntimeException;
use InvalidArgumentException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Dashed\DashedEcommerceCore\Models\Quote;
use Dashed\DashedEcommerceCore\Events\Quotes\QuoteAccepted;
use Dashed\DashedEcommerceCore\Events\Quotes\QuoteRejected;

/**
 * Akkoord en afwijzen. De statusovergang zelf loopt in een transactie met een
 * lock en een vlag die alleen de aanroep die de overgang echt heeft gedaan
 * laat doorlopen: twee bijna-gelijktijdige verzoeken zien allebei de
 * vergrendelde rij, maar precies een van de twee vindt hem nog op
 * "verstuurd" staan. De andere krijgt de al-bijgewerkte rij terug en stopt
 * meteen. Een akkoord maakt geen order; dat doet QuoteConverter, op verzoek
 * van de klant of de beheerder.
 */
class QuoteAcceptance
{
    /**
     * @param  array<int, int>  $selectedLineIds
     *
     * De handtekening is een PNG-data-URL uit het tekenvlak; wat
     * QuoteSignature niet als echte PNG herkent wordt niet opgeslagen.
     */
    /**
     * Keuzegroepen waarin niet precies een regel gekozen is. Een keuzegroep
     * is "een uit meerdere": nul kiezen haalt de dure optie gratis weg, twee
     * kiezen telt ze allebei. De pagina dwingt dat af met radioknoppen, maar
     * de keuze komt uit de Livewire-staat, dus hier nog een keer.
     *
     * @param  array<int, int>  $selectedLineIds
     * @return array<int, string>
     */
    public static function invalidChoiceGroups(Quote $quote, array $selectedLineIds): array
    {
        return $quote->lines
            ->filter(fn ($line) => $line->is_optional && filled($line->choice_group))
            ->groupBy('choice_group')
            ->filter(fn ($lines) => $lines->filter(fn ($line) => in_array((int) $line->id, $selectedLineIds, true))->count() !== 1)
            ->keys()
            ->map(fn ($group) => (string) $group)
            ->values()
            ->all();
    }

    public static function accept(Quote $quote, array $selectedLineIds, string $name, string $ip, ?string $signature = null, bool $notifyCustomer = true): Quote
    {
        $selectedLineIds = array_map('intval', $selectedLineIds);

        if ($quote->status === Quote::STATUS_ACCEPTED) {
            return $quote;
        }

        if (! $quote->isAnswerable()) {
            throw new RuntimeException(__('Deze offerte kan niet meer geaccepteerd worden'));
        }

        $originalLocale = App::getLocale();
        $didAccept = false;

        // De taal van de offerte geldt voor de transactie en de akkoord-PDF
        // (die leest :variabelen: via Translation::get(), en die kijkt naar
        // app()->getLocale()). Buiten deze try/finally lekte die taal door
        // naar de beheerdersmelding en de app-push die na het akkoord volgen:
        // die horen in de taal van de beheerder te staan, niet in die van de
        // offerte. De klantmail-luisteraar zet voor zichzelf al een eigen
        // locale (SendQuoteCustomerMail), dus die heeft hier niets aan nodig.
        App::setLocale($quote->locale);

        try {
            $quote = DB::transaction(function () use ($quote, $selectedLineIds, $name, $ip, $signature, &$didAccept) {
                /** @var Quote $locked */
                $locked = Quote::query()->whereKey($quote->id)->lockForUpdate()->first();

                if ($locked->status !== Quote::STATUS_SENT) {
                    return $locked;
                }

                if ($groups = self::invalidChoiceGroups($locked, $selectedLineIds)) {
                    throw new InvalidArgumentException(__('Kies precies een optie in elke keuzegroep: :groepen', [
                        'groepen' => implode(', ', $groups),
                    ]));
                }

                // Wat de klant koos vastleggen op de regels zelf: de akkoord-PDF
                // en de order lezen daarna gewoon de regels, zonder losse lijst.
                foreach ($locked->lines as $line) {
                    if (! $line->is_optional) {
                        continue;
                    }
                    $line->is_selected = in_array($line->id, $selectedLineIds, true);
                    $line->save();
                }

                $locked->load('lines');

                $locked->status = Quote::STATUS_ACCEPTED;
                $locked->accepted_at = now();
                $locked->accepted_name = $name;
                $locked->accepted_ip = $ip;
                $locked->accepted_signature = QuoteSignature::normalize($signature);
                $locked->total = QuoteTotals::for($locked)->total;
                $locked->save();

                $didAccept = true;

                return $locked;
            });

            if ($didAccept) {
                // Binnen rescue(): een mislukte PDF-schrijving mag het akkoord
                // en de meldingen niet tegenhouden. Mail en pagina checken toch
                // al exists() en laten de downloadlink dan gewoon weg; een
                // volgende poging kan niet meer herkansen, want de status staat
                // al op geaccepteerd.
                rescue(fn () => QuotePdf::store($quote, accepted: true));
            }
        } finally {
            App::setLocale($originalLocale);
        }

        // Deze aanroep heeft de overgang niet zelf gedaan: een ander
        // verzoek won de lock. Die is dan zelf verantwoordelijk voor de
        // melding; hier is niets meer te doen.
        if (! $didAccept) {
            return $quote;
        }

        // Locale hier weer op de oorspronkelijke stand: de beheerdersmelding
        // (belletje, mail, app-push) die op dit event volgt hoort in die taal.
        QuoteAccepted::dispatch($quote, $notifyCustomer);

        return $quote;
    }

    public static function reject(Quote $quote, string $reason, bool $notifyCustomer = true): Quote
    {
        if ($quote->status === Quote::STATUS_REJECTED) {
            return $quote;
        }

        if (! $quote->isAnswerable()) {
            throw new RuntimeException(__('Deze offerte kan niet meer afgewezen worden'));
        }

        $didReject = false;

        $quote = DB::transaction(function () use ($quote, $reason, &$didReject) {
            /** @var Quote $locked */
            $locked = Quote::query()->whereKey($quote->id)->lockForUpdate()->first();

            if ($locked->status !== Quote::STATUS_SENT) {
                return $locked;
            }

            $locked->status = Quote::STATUS_REJECTED;
            $locked->rejected_at = now();
            $locked->rejection_reason = $reason;
            $locked->save();

            $didReject = true;

            return $locked;
        });

        if (! $didReject) {
            return $quote;
        }

        QuoteRejected::dispatch($quote, $notifyCustomer);

        return $quote;
    }
}
