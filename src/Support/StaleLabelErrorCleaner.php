<?php

namespace Dashed\DashedEcommerceCore\Support;

/**
 * Zodra een niet-retourlabel een PDF krijgt, zijn de oude foutmeldingen op de
 * andere niet-retourlabels van dezelfde order en vervoerder achterhaald.
 * Gebruikt een query-update, zodat er geen model-events (push) afgaan.
 */
class StaleLabelErrorCleaner
{
    public static function fromLabel($label): void
    {
        if ($label->is_return || ! $label->wasChanged('label_pdf_path') || trim((string) $label->label_pdf_path) === '') {
            return;
        }

        $label->newQuery()
            ->where('order_id', $label->order_id)
            ->where('is_return', false)
            ->where('id', '!=', $label->id)
            ->whereNotNull('error')
            ->update(['error' => null]);
    }
}
