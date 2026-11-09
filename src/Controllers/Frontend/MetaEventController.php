<?php

namespace Dashed\DashedEcommerceCore\Controllers\Frontend;

use Throwable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Dashed\DashedEcommerceCore\Services\Meta\MetaBrowserEvents;

/**
 * Ontvangt de melding van de browser dat een productpagina getoond is, voor
 * het ViewContent-event van de Conversions API. Antwoordt altijd leeg en
 * zonder fout: de bezoeker merkt hier nooit iets van.
 */
class MetaEventController
{
    public function viewContent(Request $request, MetaBrowserEvents $events): Response
    {
        try {
            $productId = $request->input('product_id');
            $eventId = $request->input('event_id');

            if (is_numeric($productId) && is_string($eventId)) {
                $events->viewContent($request, (int) $productId, $eventId);
            }
        } catch (Throwable $e) {
            report($e);
        }

        return response()->noContent();
    }
}
