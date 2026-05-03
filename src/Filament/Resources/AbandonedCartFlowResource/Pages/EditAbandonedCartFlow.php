<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\AbandonedCartFlowResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlow;
use Dashed\DashedEcommerceCore\Services\AbandonedCart\BackfillFlowService;
use Dashed\DashedEcommerceCore\Filament\Resources\AbandonedCartFlowResource;
use Dashed\DashedEcommerceCore\Filament\Resources\AbandonedCartFlowResource\Widgets\AbandonedCartFlowStats;

class EditAbandonedCartFlow extends EditRecord
{
    protected static string $resource = AbandonedCartFlowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backfillExisting')
                ->label('Toepassen op bestaande')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn (): bool => (bool) $this->record?->is_active)
                ->modalHeading('Flow toepassen op bestaande carts/orders')
                ->modalDescription('Plant alsnog de stappen van deze flow voor verlaten winkelwagens en/of geannuleerde bestellingen die binnen het opgegeven aantal dagen vallen. Records waarvoor deze flow al gepland staat worden overgeslagen.')
                ->form([
                    TextInput::make('since_days')
                        ->label('Aantal dagen terug')
                        ->helperText('Backfill geldt voor records die in de afgelopen X dagen zijn aangemaakt.')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(365)
                        ->default(30)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    /** @var AbandonedCartFlow $flow */
                    $flow = $this->record;

                    $stats = app(BackfillFlowService::class)->run(
                        flow: $flow,
                        sinceDays: (int) ($data['since_days'] ?? 30),
                    );

                    Notification::make()
                        ->title('Backfill voltooid')
                        ->body(sprintf(
                            'Carts gepland: %d (al gepland: %d). Orders gepland: %d (al gepland: %d).',
                            $stats['carts_scheduled'],
                            $stats['carts_skipped_existing'],
                            $stats['orders_scheduled'],
                            $stats['orders_skipped_existing'],
                        ))
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AbandonedCartFlowStats::class,
        ];
    }

    protected function afterSave(): void
    {
        if ($this->record->is_active) {
            AbandonedCartFlow::where('id', '!=', $this->record->id)->update(['is_active' => false]);
        }
    }
}
