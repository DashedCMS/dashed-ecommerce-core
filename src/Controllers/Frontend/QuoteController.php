<?php

namespace Dashed\DashedEcommerceCore\Controllers\Frontend;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedEcommerceCore\Models\Quote;
use Dashed\DashedEcommerceCore\Classes\QuoteAccess;
use Dashed\DashedEcommerceCore\Services\Quotes\QuotePdf;

class QuoteController
{
    public function show(string $hash)
    {
        $quote = Quote::with('lines')->where('hash', $hash)->whereNotNull('sent_at')->firstOrFail();

        // De taal van de offerte, niet die van de bezoeker: de inhoud staat er
        // in een taal en de labels horen daarbij te passen.
        app()->setLocale($quote->locale);

        // Zacht signaal: ook een e-mailbeveiliger kan de pagina openen. Daarom
        // heet dit in het CMS "geopend" en niet "gelezen".
        if (! $quote->viewed_at) {
            $quote->viewed_at = now();
            $quote->saveQuietly();
        }

        if (! $quote->isAnswerable()) {
            return view('dashed-ecommerce-core::quotes.closed', ['quote' => $quote]);
        }

        return view('dashed-ecommerce-core::quotes.show', ['quote' => $quote]);
    }

    public function download(Request $request, string $hash)
    {
        $quote = Quote::where('hash', $hash)->firstOrFail();

        abort_unless(QuoteAccess::allows($request, $quote), 403);

        $accepted = (bool) $request->query('accepted', false);
        $path = QuotePdf::path($quote, $accepted);

        abort_unless(Storage::disk('dashed')->exists($path), 404);

        return Storage::disk('dashed')->download($path);
    }
}
