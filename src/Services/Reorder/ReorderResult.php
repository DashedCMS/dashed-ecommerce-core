<?php

namespace Dashed\DashedEcommerceCore\Services\Reorder;

use Dashed\DashedTranslations\Models\Translation;

/**
 * Wat "Bestel opnieuw" deed: welke regels in de winkelwagen kwamen (met de
 * extra's die wegvielen) en welke niet, met de reden.
 */
class ReorderResult
{
    /** @var array<int, array{name: string, quantity: int, dropped_extras: array<int, string>}> */
    public array $added = [];

    /** @var array<int, array{name: string, reason: string, url: ?string}> */
    public array $skipped = [];

    public function added(string $name, int $quantity, array $droppedExtras = []): void
    {
        $this->added[] = ['name' => $name, 'quantity' => $quantity, 'dropped_extras' => array_values($droppedExtras)];
    }

    public function skip(string $name, string $reason, ?string $url = null): void
    {
        $this->skipped[] = ['name' => $name, 'reason' => $reason, 'url' => $url];
    }

    public function addedCount(): int
    {
        return count($this->added);
    }

    /**
     * Eén regel per probleem, in de taal van de bezoeker, voor een melding
     * op de winkelwagenpagina.
     *
     * @return array<int, string>
     */
    public function messages(): array
    {
        $messages = [];

        foreach ($this->skipped as $skip) {
            $messages[] = match ($skip['reason']) {
                Reorder::SKIP_OUT_OF_STOCK => Translation::get('reorder-skipped-out-of-stock', 'reorder', ':name: is niet meer op voorraad', 'text', ['name' => $skip['name']]),
                Reorder::SKIP_NEEDS_CHOICE => Translation::get('reorder-skipped-needs-choice', 'reorder', 'Kies voor :name: opnieuw je opties op de productpagina', 'text', ['name' => $skip['name']]),
                default => Translation::get('reorder-skipped-unavailable', 'reorder', ':name: is niet meer verkrijgbaar', 'text', ['name' => $skip['name']]),
            };
        }

        foreach ($this->added as $line) {
            foreach ($line['dropped_extras'] as $extra) {
                $messages[] = Translation::get('reorder-extra-dropped', 'reorder', 'Bij :name: kan :extra: niet meer gekozen worden', 'text', [
                    'name' => $line['name'],
                    'extra' => $extra,
                ]);
            }
        }

        return $messages;
    }
}
