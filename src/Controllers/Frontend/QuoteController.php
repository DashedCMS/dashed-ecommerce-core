<?php

namespace Dashed\DashedEcommerceCore\Controllers\Frontend;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedEcommerceCore\Models\Quote;
use Dashed\DashedEcommerceCore\Classes\QuoteAccess;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Dashed\DashedEcommerceCore\Services\Quotes\QuotePdf;
use Dashed\DashedEcommerceCore\Services\Quotes\QuoteConverter;

class QuoteController
{
    public function show(Request $request, string $hash)
    {
        $quote = Quote::with('lines')->where('hash', $hash)->firstOrFail();

        // Het beheerdersvoorbeeld (Quote::previewUrl(), of ?preview=1 als
        // ingelogde beheerder) toont dezelfde pagina als de klant, ook voor een
        // concept. Zonder dat recht bestaat een onverstuurde offerte hier niet:
        // 404, net als een onbekende hash, zodat de hash alleen niets verraadt.
        $preview = $request->boolean('preview') && QuoteAccess::allowsPreview($request);

        abort_if(! $quote->sent_at && ! $preview, 404);

        // De taal van de offerte, niet die van de bezoeker: de inhoud staat er
        // in een taal en de labels horen daarbij te passen.
        app()->setLocale($quote->locale);

        // Zacht signaal: ook een e-mailbeveiliger kan de pagina openen. Daarom
        // heet dit in het CMS "geopend" en niet "gelezen". Een beheerder die
        // zijn eigen offerte nakijkt is geen opening.
        if (! $quote->viewed_at && ! $preview) {
            $quote->viewed_at = now();
            $quote->saveQuietly();
        }

        if ($quote->sent_at && ! $quote->isAnswerable()) {
            return view('dashed-ecommerce-core::quotes.closed', ['quote' => $quote]);
        }

        return view('dashed-ecommerce-core::quotes.show', ['quote' => $quote, 'preview' => $preview]);
    }

    public function order(string $hash)
    {
        $quote = Quote::where('hash', $hash)->whereNotNull('sent_at')->firstOrFail();

        $mode = $quote->payment_route === Quote::ROUTE_ON_ACCOUNT
            ? QuoteConverter::ON_ACCOUNT
            : QuoteConverter::CHECKOUT;

        try {
            $result = QuoteConverter::convert($quote, $mode);
        } catch (LockTimeoutException) {
            // Een tweede klik terwijl de eerste nog bezig is: geen 500, gewoon
            // terug naar de offertepagina, die de actuele stand toont.
            return redirect($quote->publicUrl());
        }

        return redirect($result->redirectUrl ?? $quote->publicUrl());
    }

    public function download(Request $request, string $hash)
    {
        $quote = Quote::where('hash', $hash)->firstOrFail();

        // Het beheerdersvoorbeeld is geen klantdocument: dat gaat altijd achter
        // de ondertekende link of een ingelogde beheerder, ook als de
        // handtekening voor de klantdocumenten niet verplicht staat.
        if ($request->boolean('preview')) {
            abort_unless(QuoteAccess::allowsPreview($request), 403);

            $previewPath = QuotePdf::previewPath($quote);
            abort_unless(Storage::disk('dashed')->exists($previewPath), 404);

            return Storage::disk('dashed')->download($previewPath);
        }

        abort_unless(QuoteAccess::allows($request, $quote), 403);

        $accepted = (bool) $request->query('accepted', false);
        $path = QuotePdf::path($quote, $accepted);

        abort_unless(Storage::disk('dashed')->exists($path), 404);

        return Storage::disk('dashed')->download($path);
    }
}
