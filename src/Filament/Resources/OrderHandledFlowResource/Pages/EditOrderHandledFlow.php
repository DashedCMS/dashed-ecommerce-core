<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderHandledFlowResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedEcommerceCore\Models\OrderHandledFlow;
use Dashed\DashedEcommerceCore\Services\OrderHandledFlow\BackfillOrderHandledFlowService;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderHandledFlowResource;

class EditOrderHandledFlow extends EditRecord
{
    protected static string $resource = OrderHandledFlowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backfillExisting')
                ->label('Toepassen op bestaande')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn (): bool => (bool) $this->record?->is_active)
                ->modalHeading('Flow toepassen op bestaande afgehandelde bestellingen')
                ->modalDescription('Plant alsnog de stappen van deze flow voor bestellingen die binnen het opgegeven aantal dagen op fulfillment_status = handled zijn gezet maar nog niet in de flow zitten. Records die al gestart of geannuleerd zijn worden overgeslagen.')
                ->form([
                    TextInput::make('since_days')
                        ->label('Aantal dagen terug')
                        ->helperText('Backfill geldt voor orders waarvan updated_at binnen de afgelopen X dagen valt.')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(365)
                        ->default(30)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    /** @var OrderHandledFlow $flow */
                    $flow = $this->record;

                    $stats = app(BackfillOrderHandledFlowService::class)->run(
                        flow: $flow,
                        sinceDays: (int) ($data['since_days'] ?? 30),
                    );

                    Notification::make()
                        ->title('Backfill voltooid')
                        ->body(sprintf(
                            'Gestart: %d. Al gestart: %d. Geannuleerd: %d. Geen email: %d. Mails ingepland: %d.',
                            $stats['orders_started'],
                            $stats['orders_skipped_already_started'],
                            $stats['orders_skipped_cancelled'],
                            $stats['orders_skipped_no_email'],
                            $stats['emails_dispatched'],
                        ))
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        if ($this->record->is_active) {
            OrderHandledFlow::query()
                ->where('id', '!=', $this->record->id)
                ->update(['is_active' => false]);
        }
    }
}
