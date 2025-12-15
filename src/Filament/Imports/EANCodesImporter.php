<?php

namespace Dashed\DashedEcommerceCore\Filament\Imports;

use Illuminate\Support\Number;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Models\Import;
use Dashed\DashedEcommerceCore\Models\Product;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;

class EANCodesImporter extends Importer
{
    protected static ?string $model = Product::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('ean')
            ->label('EAN code'),
        ];
    }

    public function resolveRecord(): Product
    {
        if (Product::where('ean', $this->data['ean'])->exists()) {
            throw new RowImportFailedException("A product with EAN code {$this->data['ean']} already exists.");
        }

        return Product::query()
            ->whereNull('ean')
            ->first();
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your EAN codes import has completed and ' . Number::format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
