<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Filament\Notifications\Notification;

class Printing
{
    public static function print(string $printerName, string $pdfPath): bool
    {
        try {
            $output = [];
            $returnValue = 0;
            exec("lp -d $printerName $pdfPath", $output, $returnValue);

            if ($returnValue !== 0) {
                Notification::make()
                    ->title('Printen mislukt')
                    ->body(implode("\n", $output))
                    ->danger()
                    ->send();

                return;
            }

            Notification::make()
                ->title('Printen gelukt')
                ->success()
                ->send();

            return;
        } catch (\Exception $e) {
            Notification::make()
                ->title('Printen mislukt')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }
    }
}
