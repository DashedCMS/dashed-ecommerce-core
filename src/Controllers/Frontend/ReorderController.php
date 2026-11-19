<?php

namespace Dashed\DashedEcommerceCore\Controllers\Frontend;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Services\Reorder\Reorder;

/**
 * De link "Bestel opnieuw" uit het account en de mails. Ondertekend en op de
 * orderhash, dus niet te raden. De producten komen bij wat er al in de
 * winkelwagen ligt: leegmaken zou de klant iets afpakken wat hij net koos.
 */
class ReorderController extends Controller
{
    public function __invoke(Request $request, string $orderHash)
    {
        $order = Order::where('hash', $orderHash)->first();

        if (! $order || ! Reorder::allowedFor($order)) {
            return redirect('/')->with('error', Translation::get('reorder-not-possible', 'reorder', 'Deze bestelling kan niet opnieuw besteld worden'));
        }

        $result = Reorder::fill($order);

        $cartUrl = ShoppingCart::getCartUrl();
        if (! $cartUrl || $cartUrl === '#') {
            $cartUrl = '/';
        }

        $redirect = redirect($cartUrl)->with('reorder_result', [
            'added' => $result->added,
            'skipped' => $result->skipped,
        ]);

        if ($result->addedCount() > 0) {
            $redirect->with('success', Translation::get('reorder-added', 'reorder', 'De producten van je bestelling staan in je winkelwagen'));
        }

        $messages = $result->messages();

        if ($result->addedCount() === 0) {
            array_unshift($messages, Translation::get('reorder-nothing-added', 'reorder', 'Geen van de producten uit deze bestelling is nog te bestellen'));
        }

        if ($messages !== []) {
            $redirect->with('error', implode(' ', $messages));
        }

        return $redirect;
    }
}
