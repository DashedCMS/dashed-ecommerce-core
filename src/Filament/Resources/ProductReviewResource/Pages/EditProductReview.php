<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductReviewResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedEcommerceCore\Models\ProductReview;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductReviewResource;

class EditProductReview extends EditRecord
{
    protected static string $resource = ProductReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['status'] ?? null) === ProductReview::STATUS_APPROVED && ! $this->record->approved_at) {
            $data['approved_at'] = now();
        }

        $reply = trim((string) ($data['reply'] ?? ''));
        $data['reply'] = $reply === '' ? null : $reply;

        if ($data['reply'] !== $this->record->reply) {
            $data['replied_at'] = $data['reply'] ? now() : null;
            $data['replied_by'] = $data['reply'] ? auth()->id() : null;
        }

        return $data;
    }
}
