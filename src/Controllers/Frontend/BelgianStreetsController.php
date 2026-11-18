<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Controllers\Frontend;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Dashed\DashedEcommerceCore\Services\Address\BelgianAddresses;

/**
 * Belgische straatsuggesties binnen een postcode voor de checkout:
 * /ecommerce/address/belgian-streets?postcode=9000&q=kor&locale=nl
 * Zonder sessie of cookies en altijd 200: een mislukte zoekactie laat de klant gewoon typen.
 */
class BelgianStreetsController extends Controller
{
    public function __invoke(Request $request, BelgianAddresses $addresses): JsonResponse
    {
        $streets = BelgianAddresses::active()
            ? $addresses->streets((string) $request->query('postcode', ''), (string) $request->query('q', ''), (string) $request->query('locale', ''))
            : [];

        return response()
            ->json(['streets' => $streets])
            ->header('Cache-Control', 'private, max-age=3600');
    }
}
