<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductQuestionResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductQuestionResource;

class EditProductQuestion extends EditRecord
{
    protected static string $resource = ProductQuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $answer = trim((string) ($data['answer'] ?? ''));
        $data['answer'] = $answer === '' ? null : $answer;

        if ($data['answer'] && ! $this->record->answered_at) {
            $data['answered_at'] = now();
            $data['answered_by'] = auth()->id();
        }

        return $data;
    }
}
