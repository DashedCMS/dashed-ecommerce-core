<?php

namespace Dashed\DashedEcommerceCore\Support;

use Dashed\DashedCore\Classes\Locales;
use Dashed\DashedPages\Models\Page;

class DefaultReturnPage
{
    public static function createIfMissing(): ?Page
    {
        if (\Dashed\DashedCore\Models\Customsetting::get('return_page_id')) {
            return null;
        }

        // Secondary defense: skip if the nl or en slug already exists.
        if (Page::query()->where('slug->nl', 'retourneren')->orWhere('slug->en', 'returns')->exists()) {
            return null;
        }

        $locales = Locales::getActivatedLocalesFromSites();

        // Fallback so the page is always created, even in environments without
        // active locales (e.g. fresh test databases).
        if (empty($locales)) {
            $locales = ['nl', 'en'];
        }

        $block = [
            'data' => [
                'title' => 'Koop ongedaan maken',
                'intro' => 'Vul je bestelnummer en e-mailadres in om je koop ongedaan te maken.',
                'in_container' => true,
                'top_margin' => true,
                'bottom_margin' => true,
            ],
            'type' => 'retour-formulier',
        ];

        $page = new Page();

        foreach ($locales as $locale) {
            [$name, $slug] = match ($locale) {
                'nl' => ['Retourneren', 'retourneren'],
                default => ['Returns', 'returns'],
            };

            $page->setTranslation('name', $locale, $name);
            $page->setTranslation('slug', $locale, $slug);
            $page->setTranslation('content', $locale, [$block]);
        }

        $page->save();

        \Dashed\DashedCore\Models\Customsetting::set('return_page_id', $page->id);

        return $page;
    }
}
