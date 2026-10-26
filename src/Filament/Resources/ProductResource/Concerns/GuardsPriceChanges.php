<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductResource\Concerns;

use Filament\Actions\Action;
use Livewire\Attributes\Locked;
use Illuminate\Support\HtmlString;
use Filament\Support\Exceptions\Halt;
use Dashed\DashedEcommerceCore\Classes\Guards\PriceGuard;

/**
 * Houdt het opslaan van een product tegen zolang een riskante prijs (zie
 * PriceGuard) niet bevestigd is. De bevestiging staat in een modal met oud en
 * nieuw naast elkaar, en "Toch opslaan" draait de gewone opslag opnieuw.
 */
trait GuardsPriceChanges
{
    #[Locked]
    public bool $priceGuardConfirmed = false;

    /** @var array<int, string> */
    #[Locked]
    public array $priceGuardReasons = [];

    #[Locked]
    public ?float $priceGuardOld = null;

    #[Locked]
    public ?float $priceGuardNew = null;

    protected function guardPrice(): void
    {
        if ($this->priceGuardConfirmed) {
            return;
        }

        $record = method_exists($this, 'getRecord') ? $this->getRecord() : null;
        $old = $record?->exists ? $record->getRawOriginal('price') : null;
        $new = $this->data['price'] ?? null;

        $reasons = PriceGuard::reasons($old, $new, $this->data['purchase_price'] ?? null);

        if (! $reasons) {
            return;
        }

        $this->priceGuardReasons = $reasons;
        $this->priceGuardOld = is_numeric($old) ? (float) $old : null;
        $this->priceGuardNew = (float) $new;

        $this->mountAction('confirmPriceChange');

        throw new Halt();
    }

    public function confirmPriceChangeAction(): Action
    {
        return Action::make('confirmPriceChange')
            ->label(__('Toch opslaan'))
            ->modalHeading(__('Klopt deze prijs?'))
            ->modalDescription(fn () => new HtmlString(
                '<p><strong>'.e(__('Oud')).':</strong> '.e(PriceGuard::format($this->priceGuardOld)).
                ' &nbsp; <strong>'.e(__('Nieuw')).':</strong> '.e(PriceGuard::format($this->priceGuardNew)).'</p>'.
                '<ul style="margin-top:.5rem;list-style:disc;padding-left:1.25rem">'.
                collect($this->priceGuardReasons)->map(fn ($reason) => '<li>'.e($reason).'</li>')->implode('').
                '</ul>'
            ))
            ->modalSubmitActionLabel(__('Toch opslaan'))
            ->color('warning')
            ->requiresConfirmation()
            ->action(function (): void {
                $this->priceGuardConfirmed = true;

                try {
                    $this->confirmGuardedSave();
                } finally {
                    $this->priceGuardConfirmed = false;
                }
            });
    }

    abstract protected function confirmGuardedSave(): void;
}
