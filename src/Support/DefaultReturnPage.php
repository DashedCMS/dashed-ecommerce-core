<?php

namespace Dashed\DashedEcommerceCore\Support;

use Dashed\DashedPages\Models\Page;

class DefaultReturnPage
{
    public static function createIfMissing(): void
    {
        $exists = Page::query()
            ->where('slug->nl', 'retourneren')
            ->orWhere('slug->en', 'returns')
            ->exists();

        if ($exists) {
            return;
        }

        $block = [
            'type' => 'retour-formulier',
            'data' => [
                'title' => 'Koop ongedaan maken',
                'intro' => 'Vul je bestelnummer en e-mailadres in om je koop ongedaan te maken.',
                'in_container' => true,
                'top_margin' => true,
                'bottom_margin' => true,
            ],
        ];

        $page = new Page();
        $page->setTranslation('name', 'nl', 'Retourneren');
        $page->setTranslation('name', 'en', 'Returns');
        $page->setTranslation('slug', 'nl', 'retourneren');
        $page->setTranslation('slug', 'en', 'returns');
        $page->setTranslation('content', 'nl', [$block]);
        $page->setTranslation('content', 'en', [$block]);
        $page->save();
    }
}
