<?php

use Dashed\DashedPages\Models\Page;
use Dashed\DashedEcommerceCore\Support\DefaultReturnPage;

it('creates a default return page with the return block, idempotently', function () {
    DefaultReturnPage::createIfMissing();
    DefaultReturnPage::createIfMissing();

    $pages = Page::query()->where('slug->nl', 'retourneren')->get();

    expect($pages)->toHaveCount(1);

    $page = $pages->first();
    $content = $page->getTranslation('content', 'nl');
    $types = collect($content)->pluck('type')->all();

    expect($types)->toContain('retour-formulier');
});

it('creates the page with English translation', function () {
    DefaultReturnPage::createIfMissing();

    $page = Page::query()->where('slug->nl', 'retourneren')->first();

    expect($page->getTranslation('name', 'en'))->toBe('Returns')
        ->and($page->getTranslation('slug', 'en'))->toBe('returns');
});

it('does not create a second return page if a page with the return slug already exists', function () {
    // At this point the migration may have already created a return page.
    // Record the count of pages matching the return slug before calling createIfMissing.
    $countBefore = Page::query()
        ->where('slug->nl', 'retourneren')
        ->orWhere('slug->en', 'returns')
        ->count();

    DefaultReturnPage::createIfMissing();

    $countAfter = Page::query()
        ->where('slug->nl', 'retourneren')
        ->orWhere('slug->en', 'returns')
        ->count();

    expect($countAfter)->toBe($countBefore);
});
