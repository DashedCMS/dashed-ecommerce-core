<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Quotes;

use RuntimeException;
use Livewire\Component;
use Dashed\DashedEcommerceCore\Models\Quote;
use Dashed\DashedEcommerceCore\Models\QuoteLine;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Services\Quotes\QuoteTotals;
use Dashed\DashedEcommerceCore\Services\Quotes\QuoteSignature;
use Dashed\DashedEcommerceCore\Services\Quotes\QuoteAcceptance;

class QuotePage extends Component
{
    public string $hash = '';

    /** @var array<int, bool> regel-id naar wel of niet gekozen */
    public array $selected = [];

    public ?Quote $quote = null;

    public string $acceptName = '';

    public bool $acceptAgreed = false;

    /** PNG-data-URL uit het tekenvlak, gezet door de browser. */
    public string $signature = '';

    public string $rejectReason = '';

    public bool $showReject = false;

    public function mount(string $hash): void
    {
        $this->hash = $hash;
        $this->quote = Quote::with('lines')->where('hash', $hash)->whereNotNull('sent_at')->firstOrFail();

        foreach ($this->quote->lines as $line) {
            $this->selected[$line->id] = $line->is_optional ? (bool) $line->is_selected : true;
        }
    }

    /**
     * De taal van de offerte, op elk verzoek. De controller zet hem voor de
     * eerste render, maar booted() draait ook na elke hydratie, en zonder dit
     * rendert elke Livewire-update in de taal van de bezoeker terwijl de inhoud
     * in die van de offerte staat.
     */
    public function booted(): void
    {
        if ($this->quote?->locale) {
            app()->setLocale($this->quote->locale);
        }
    }

    public function toggleLine(int $lineId): void
    {
        $line = $this->quote->lines->firstWhere('id', $lineId);

        if (! $line || ! $line->is_optional || $line->choice_group) {
            return;
        }

        $this->selected[$lineId] = ! ($this->selected[$lineId] ?? false);
    }

    public function chooseInGroup(string $group, int $lineId): void
    {
        foreach ($this->quote->lines as $line) {
            if ($line->choice_group === $group) {
                $this->selected[$line->id] = $line->id === $lineId;
            }
        }
    }

    /** De totalen zoals ze nu op het scherm staan, met de keuzes van de klant. */
    public function totals(): QuoteTotals
    {
        return QuoteTotals::forLines($this->chosenLines());
    }

    /**
     * Kopieen, niet de geladen regels zelf: Collection::map() kloont niet, en
     * deze vlag is alleen voor de weergave. Zonder kloon markeert elke render
     * de echte regels als vuil met de keuze van dit moment, en Task 7 (dat
     * hierop de akkoordflow bouwt en per regel opslaat) zou die schermstand
     * per ongeluk als het antwoord van de klant kunnen wegschrijven.
     *
     * @return \Illuminate\Support\Collection<int, QuoteLine>
     */
    public function chosenLines()
    {
        return $this->quote->lines->map(function (QuoteLine $line) {
            $copy = clone $line;
            $copy->is_selected = (bool) ($this->selected[$line->id] ?? ! $line->is_optional);

            return $copy;
        });
    }

    public function accept(): void
    {
        $this->validate([
            'acceptName' => ['required', 'string', 'min:2', 'max:255'],
            'acceptAgreed' => ['accepted'],
            'signature' => ['required', 'string', function ($attribute, $value, $fail) {
                if (! QuoteSignature::normalize($value)) {
                    $fail(Translation::get('validation-signature-required', 'quote', 'Zet uw handtekening in het vak'));
                }
            }],
        ], [
            'acceptName.required' => Translation::get('validation-name-required', 'quote', 'Vul uw naam in'),
            'acceptName.min' => Translation::get('validation-name-required', 'quote', 'Vul uw naam in'),
            'acceptAgreed.accepted' => Translation::get('validation-agree-required', 'quote', 'Vink aan dat u akkoord gaat'),
            'signature.required' => Translation::get('validation-signature-required', 'quote', 'Zet uw handtekening in het vak'),
        ]);

        $chosen = collect($this->selected)->filter()->keys()->map(fn ($id) => (int) $id)->all();

        try {
            $quote = QuoteAcceptance::accept(
                $this->quote->fresh(),
                $chosen,
                $this->acceptName,
                (string) request()->ip(),
                $this->signature,
            );
        } catch (RuntimeException) {
            // De offerte is verlopen of ingetrokken terwijl deze pagina open
            // stond. Dat heeft zijn eigen scherm; de controller stuurt een
            // offerte die niet meer te antwoorden is daar zelf naartoe.
            $this->redirect($this->quote->publicUrl(), navigate: false);

            return;
        }

        $this->redirect($this->afterAcceptUrl($quote), navigate: false);
    }

    public function reject(): void
    {
        $this->validate([
            'rejectReason' => ['required', 'string', 'min:2', 'max:1000'],
        ], [
            'rejectReason.required' => Translation::get('validation-reject-reason-required', 'quote', 'Geef aan waarom u afwijst'),
            'rejectReason.min' => Translation::get('validation-reject-reason-required', 'quote', 'Geef aan waarom u afwijst'),
        ]);

        try {
            QuoteAcceptance::reject($this->quote->fresh(), $this->rejectReason);
        } catch (RuntimeException) {
            // Verlopen of ingetrokken tijdens het invullen: naar hetzelfde
            // eindscherm, niet naar een foutpagina. Alleen deze uitzondering,
            // zodat een echte storing wel gewoon een storing blijft.
        }

        $this->redirect($this->quote->publicUrl(), navigate: false);
    }

    /**
     * Vooraf betalen gaat naar de bestaande proforma-checkout; op rekening
     * naar het bedanktscherm. Is de order door een kredietweigering concept
     * gebleven, dan ook naar het bedanktscherm: de klant hoeft daar niets mee.
     */
    private function afterAcceptUrl(Quote $quote): string
    {
        $order = $quote->order;

        if ($order && $order->is_proforma) {
            return route('dashed.frontend.proforma-checkout', ['orderHash' => $order->hash]);
        }

        return $quote->publicUrl();
    }

    public function render()
    {
        return view('dashed-ecommerce-core::livewire.frontend.quotes.quote-page', [
            'totals' => $this->totals(),
        ]);
    }
}
