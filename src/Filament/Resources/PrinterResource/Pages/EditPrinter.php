<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Filament\Resources\PrinterResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedEcommerceCore\Models\PrintJob;
use Dashed\DashedEcommerceCore\Enums\PrinterType;
use Dashed\DashedEcommerceCore\Enums\PrintJobType;
use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;
use Dashed\DashedEcommerceCore\Filament\Resources\PrinterResource;

class EditPrinter extends EditRecord
{
    protected static string $resource = PrinterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate_token')
                ->label('Genereer token')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Trekt het huidige token in (als er een is) en genereert een nieuwe. Het commando om de daemon opnieuw te installeren staat daarna in de Token-sectie hieronder.')
                ->action(function (): void {
                    $printer = $this->getRecord();
                    $printer->tokens()->delete();
                    $token = $printer->createToken("printer-{$printer->ulid}")->plainTextToken;
                    $printer->forceFill(['plain_token' => $token])->save();

                    $this->fillForm();

                    Notification::make()
                        ->title('Token gegenereerd')
                        ->body('Scroll naar de Token-sectie hieronder voor het token en het install-commando.')
                        ->success()
                        ->send();
                }),
            Action::make('test_print')
                ->label('Test print')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->visible(fn (): bool => (bool) $this->getRecord()->plain_token)
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
                        ->body('De daemon pakt deze binnen 5 seconden op.')
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
