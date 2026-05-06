<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderHandledFlowResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedEcommerceCore\Classes\Orders;
use Dashed\DashedEcommerceCore\Models\OrderHandledFlow;
use Dashed\DashedEcommerceCore\Services\OrderHandledFlow\BackfillOrderHandledFlowService;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderHandledFlowResource;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderHandledFlowResource\Widgets\OrderHandledFlowStats;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderHandledFlowResource\Widgets\OrderHandledFlowEnrollments;

class EditOrderHandledFlow extends EditRecord
{
    protected static string $resource = OrderHandledFlowResource::class;

    protected function getFooterWidgets(): array
    {
        return [
            OrderHandledFlowStats::class,
            OrderHandledFlowEnrollments::class,
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backfillExisting')
                ->label('Toepassen op bestaande bestellingen')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->modalHeading(function (): string {
                    /** @var OrderHandledFlow|null $flow */
                    $flow = $this->record;
                    $label = $flow ? (Orders::getFulfillmentStatusses()[$flow->trigger_status ?? 'handled'] ?? $flow->trigger_status) : 'gekozen status';

                    return 'Flow met terugwerkende kracht toepassen (' . $label . ')';
                })
                ->modalDescription(function (): string {
                    /** @var OrderHandledFlow|null $flow */
                    $flow = $this->record;
                    $triggerStatus = $flow->trigger_status ?? 'handled';
                    $label = Orders::getFulfillmentStatusses()[$triggerStatus] ?? $triggerStatus;

                    return 'Plant alsnog de stappen van deze flow voor bestellingen die binnen het opgegeven aantal dagen op fulfillment-status "' . $label . '" zijn gezet en nog niet in deze flow zitten. Records die al ingeschreven of geannuleerd zijn worden overgeslagen.';
                })
                ->form([
                    TextInput::make('since_days')
                        ->label('Aantal dagen terug')
                        ->helperText('Backfill geldt voor orders waarvan updated_at binnen de afgelopen X dagen valt.')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(3650)
                        ->default(30)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    /** @var OrderHandledFlow $flow */
                    $flow = $this->record;

                    if (! $flow->is_active) {
                        Notification::make()
                            ->title('Flow is niet actief')
                            ->body('Activeer de flow eerst voordat je hem op bestaande bestellingen toepast.')
                            ->warning()
                            ->send();

                        return;
                    }

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
        // Met meerdere trigger-statussen kunnen verschillende flows naast
        // elkaar bestaan. Alleen flows met dezelfde trigger_status mogen
        // niet allebei tegelijk actief zijn (single-active per status).
        if ($this->record->is_active) {
            OrderHandledFlow::query()
                ->where('id', '!=', $this->record->id)
                ->where('trigger_status', $this->record->trigger_status)
                ->update(['is_active' => false]);
        }
    }
}
