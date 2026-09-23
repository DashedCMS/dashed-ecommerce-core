<?php

namespace Dashed\DashedEcommerceCore\Controllers\Frontend;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedEcommerceCore\Models\Quote;
use Dashed\DashedEcommerceCore\Classes\QuoteAccess;
use Dashed\DashedEcommerceCore\Services\Quotes\QuotePdf;

class QuoteController
{
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
