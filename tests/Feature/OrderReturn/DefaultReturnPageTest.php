<?php

use Dashed\DashedPages\Models\Page;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Support\DefaultReturnPage;

it('creates a default return page with the return block, idempotently', function () {
    DefaultReturnPage::createIfMissing();
    DefaultReturnPage::createIfMissing();

    $pages = Page::query()->where('slug->nl', 'retourneren')->get();

    expect($pages)->toHaveCount(1);

    $page = $pages->first();
    $content = $page->getTranslation('content', 'nl');
    $types = collect($content)->pluck('type')->all();

    expect($types)->toContain('return-form');
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

it('sets the return_page_id Customsetting after creation', function () {
    // The migration already called createIfMissing() and set the Customsetting row.
    // Query the DB directly to bypass the in-process array-cache which can lag
    // behind DB state in tests using RefreshDatabase + transactions.
    $row = \Illuminate\Support\Facades\DB::table('dashed__custom_settings')
        ->where('name', 'return_page_id')
        ->first();

    expect($row)->not->toBeNull();

    $page = Page::query()->where('slug->nl', 'retourneren')->first();
    expect($page)->not->toBeNull()
        ->and((string) $row->value)->toBe((string) $page->id);
});

it('does not create a page when return_page_id Customsetting is already set', function () {
    DefaultReturnPage::createIfMissing();
    $countAfterFirst = Page::query()->where('slug->nl', 'retourneren')->count();

    // Calling again should be a no-op (either Customsetting guard or slug guard fires).
    DefaultReturnPage::createIfMissing();
    $countAfterSecond = Page::query()->where('slug->nl', 'retourneren')->count();

    expect($countAfterSecond)->toBe($countAfterFirst);
});

it('uses data before type key order in the content block', function () {
    DefaultReturnPage::createIfMissing();

    $page = Page::query()->where('slug->nl', 'retourneren')->first();
    $content = $page->getTranslation('content', 'nl');
    $block = $content[0];

    expect(array_key_first($block))->toBe('data')
        ->and(array_key_last($block))->toBe('type');
});
