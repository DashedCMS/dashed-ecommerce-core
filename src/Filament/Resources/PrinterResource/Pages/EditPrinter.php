<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Filament\Resources\PrinterResource\Pages;

use Dashed\DashedEcommerceCore\Enums\PrinterType;
use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;
use Dashed\DashedEcommerceCore\Enums\PrintJobType;
use Dashed\DashedEcommerceCore\Filament\Resources\PrinterResource;
use Dashed\DashedEcommerceCore\Models\PrintJob;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPrinter extends EditRecord
{
    protected static string $resource = PrinterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate_token')
                ->label('Genereer token')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Bestaande tokens van deze printer worden ingetrokken en vervangen door een nieuwe.')
                ->action(function (): void {
                    $printer = $this->getRecord();
                    $printer->tokens()->delete();
                    $token = $printer->createToken("printer-{$printer->ulid}")->plainTextToken;
                    $printer->forceFill(['plain_token' => $token])->save();

                    $this->fillForm();

                    Notification::make()
                        ->title('Token gegenereerd')
                        ->body('Het token staat in het token-veld hieronder en in de Pi installatie-handleiding.')
                        ->success()
                        ->send();
                }),
            Action::make('test_print')
                ->label('Test print')
                ->color('info')
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
                        ->body('De Pi zou deze binnen 5 sec moeten ophalen.')
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
