<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Quotes;

use Livewire\Component;
use Dashed\DashedEcommerceCore\Models\Quote;
use Dashed\DashedEcommerceCore\Models\QuoteLine;
use Dashed\DashedEcommerceCore\Services\Quotes\QuoteTotals;

class QuotePage extends Component
{
    public string $hash = '';

    /** @var array<int, bool> regel-id naar wel of niet gekozen */
    public array $selected = [];

    public ?Quote $quote = null;

    public function mount(string $hash): void
    {
        $this->hash = $hash;
        $this->quote = Quote::with('lines')->where('hash', $hash)->whereNotNull('sent_at')->firstOrFail();

        foreach ($this->quote->lines as $line) {
            $this->selected[$line->id] = $line->is_optional ? (bool) $line->is_selected : true;
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

    public function render()
    {
        return view('dashed-ecommerce-core::livewire.frontend.quotes.quote-page', [
            'totals' => $this->totals(),
        ]);
    }
}
