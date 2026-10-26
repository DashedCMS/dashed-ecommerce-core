<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductResource\Concerns;

use Filament\Support\Exceptions\Halt;
use Filament\Notifications\Notification;
use Dashed\DashedEcommerceCore\Classes\ProductCompleteness;

/**
 * Publicatiecheck op de bewerkpagina van een product: een publiek product
 * zonder naam of prijs wordt niet opgeslagen, de rest van wat ontbreekt
 * (foto, omschrijving, EAN, SEO, vertalingen) is een waarschuwing na het
 * opslaan. Daarna gaat het percentage op `completeness`.
 */
trait ChecksProductCompleteness
{
    protected function guardPublication(): void
    {
        $data = $this->data ?? [];

        if (! ($data['public'] ?? false)) {
            return;
        }

        $blocking = [];
        if (! ProductCompleteness::hasText($data['name'] ?? null)) {
            $blocking[] = __('Naam');
        }
        if (! ($data['is_bundle'] ?? false) && (float) ($data['price'] ?? 0) <= 0) {
            $blocking[] = __('Prijs');
        }

        if ($blocking === []) {
            return;
        }

        Notification::make()
            ->title(__('Niet opgeslagen: een publiek product heeft een naam en een prijs nodig'))
            ->body(__('Ontbreekt: :velden', ['velden' => implode(', ', $blocking)]))
            ->danger()
            ->persistent()
            ->send();

        throw new Halt();
    }

    protected function reportCompleteness(): void
    {
        $record = $this->getRecord();
        $score = rescue(fn () => ProductCompleteness::store($record), null, report: true);

        if ($score === null || $score >= 100 || ! $record->public) {
            return;
        }

        $checks = ProductCompleteness::checks(ProductCompleteness::locales()[1]);
        $labels = collect(ProductCompleteness::forProduct($record))
            ->map(fn (string $key) => $checks[$key]['label'] ?? null)
            ->filter()
            ->take(6)
            ->implode(', ');

        Notification::make()
            ->title(__('Opgeslagen, :procent% compleet', ['procent' => $score]))
            ->body(__('Nog niet ingevuld: :velden', ['velden' => $labels]))
            ->warning()
            ->send();
    }
}
