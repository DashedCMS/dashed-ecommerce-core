<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Filament\Resources\PrinterResource\Pages;

use Dashed\DashedEcommerceCore\Enums\PrinterType;
use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;
use Dashed\DashedEcommerceCore\Enums\PrintJobType;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\PrintQueueSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Resources\PrinterResource;
use Dashed\DashedEcommerceCore\Models\PrintJob;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditPrinter extends EditRecord
{
    protected static string $resource = PrinterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('repair')
                ->label('Opnieuw pairen')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Trekt het huidige token in en maakt een nieuwe pairing code aan. Je moet daarna de oneliner opnieuw op de Pi draaien.')
                ->action(function (): void {
                    $printer = $this->getRecord();
                    $printer->tokens()->delete();
                    $code = strtoupper(Str::random(10));

                    $printer->forceFill([
                        'plain_token' => null,
                        'pairing_code' => $code,
                        'pairing_expires_at' => now()->addHours(24),
                        'paired_at' => null,
                        'is_active' => false,
                    ])->save();

                    Notification::make()
                        ->title('Nieuwe pairing code aangemaakt')
                        ->body('Open Print queue instellingen voor het installatie-commando.')
                        ->success()
                        ->send();

                    $this->redirect(PrintQueueSettingsPage::getUrl());
                }),
            Action::make('test_print')
                ->label('Test print')
                ->color('info')
                ->visible(fn (): bool => $this->getRecord()->isPaired())
                ->action(function (): void {
                    $printer = $this->getRecord();

                    PrintJob::create([
                        'type' => $printer->type === PrinterType::ShippingLabel
                            ? PrintJobType::ShippingLabel
                            : PrintJobType::PackingSlip,
                        'order_id' => null,
                        'printer_id' => $printer->id,
                        'status' => PrintJobStatus::Pending,
                        'pdf_disk' => 'dashed-ecommerce-core',
                        'pdf_path' => 'print/test-page.pdf',
                    ]);

                    Notification::make()
                        ->title('Test job aangemaakt')
                        ->body('De Pi pakt deze binnen 5 seconden op.')
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
