<?php

namespace Dashed\DashedEcommerceCore\Classes\OrderAddOns;

use Illuminate\Support\Collection;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\OrderAddOnOffer;

/**
 * Wat de klant-ingangen van bijbestellen tonen (bedankpagina, orderbevestiging, ordertracker, wagen/checkout).
 * Elke methode geeft null als er niets te tonen is; de Blade-componenten en het mailblok zijn alleen weergave.
 * Een fout geeft nooit een kapotte pagina of mail: dan tonen we gewoon niets.
 */
final class EntryPoints
{
    public const THANK_YOU_SUGGESTIONS = 3;

    /**
     * Bedankpagina: alleen als het aanbod open is, de ingang aanstaat en er iets voor te stellen is.
     *
     * @return array{order: Order, orderNumber: string, until: string, url: string, suggestions: Collection<int, Product>}|null
     */
    public static function thankYou(?Order $order): ?array
    {
        if (! $order || ! self::offerOpen($order, Settings::ENTRY_THANK_YOU)) {
            return null;
        }

        $suggestions = rescue(fn () => Suggestions::for($order, self::THANK_YOU_SUGGESTIONS), collect(), true);

        if ($suggestions->isEmpty()) {
            return null;
        }

        OrderAddOnOffer::markShown($order);

        return [
            'order' => $order,
            'orderNumber' => Offer::orderNumber($order),
            'until' => Offer::untilText($order),
            'url' => Offer::url($order, ['via' => 'bedankpagina']),
            'suggestions' => $suggestions,
        ];
    }

    /**
     * Orderbevestiging (mailblok `order-add-on-offer`).
     *
     * @return array{order: Order, orderNumber: string, until: string, url: string}|null
     */
    public static function confirmationMail(?Order $order): ?array
    {
        if (! $order instanceof Order || ! self::offerOpen($order, Settings::ENTRY_CONFIRMATION_MAIL)) {
            return null;
        }

        return [
            'order' => $order,
            'orderNumber' => Offer::orderNumber($order),
            'until' => Offer::untilText($order, $order->locale ?: null),
            'url' => Offer::url($order, ['via' => 'bevestiging']),
        ];
    }

    /**
     * Ordertracker: een knop zolang het aanbod open is, of op een gekoppelde bijbestelling "Gaat mee met #X".
     *
     * @return array{type: 'offer', orderNumber: string, until: string, url: string}|array{type: 'add_on', parent: Order, parentNumber: string}|null
     */
    public static function tracker(?Order $order): ?array
    {
        if (! $order) {
            return null;
        }

        $parent = rescue(fn () => $order->addOnParent(), null, true);

        if ($parent) {
            return ['type' => 'add_on', 'parent' => $parent, 'parentNumber' => Offer::orderNumber($parent)];
        }

        if (! self::offerOpen($order, Settings::ENTRY_TRACKER)) {
            return null;
        }

        return [
            'type' => 'offer',
            'orderNumber' => Offer::orderNumber($order),
            'until' => Offer::untilText($order),
            'url' => Offer::url($order, ['via' => 'tracker']),
        ];
    }

    /**
     * Wagen en checkout in bijbestel-modus: `active` (gaat mee, geen verzendkosten), `excluded` (een product
     * past niet) of `closed` (het aanbod ging dicht; één keer melden, daarna stopt de modus).
     *
     * @return array{state: string, parent: ?Order, orderNumber: ?string, until: ?string, product: ?string, address: ?string}|null
     */
    public static function cartNotice(): ?array
    {
        $state = rescue(fn () => CheckoutMode::state(), null, true);

        if (! $state || ! CheckoutMode::requestedParentId()) {
            return null;
        }

        $parent = $state['parent'];

        if ($parent && $state['active']) {
            return [
                'state' => 'active',
                'parent' => $parent,
                'orderNumber' => Offer::orderNumber($parent),
                'until' => Offer::untilText($parent),
                'product' => null,
                'address' => self::address($parent),
            ];
        }

        if ($parent && $state['reason']) {
            return [
                'state' => 'excluded',
                'parent' => $parent,
                'orderNumber' => Offer::orderNumber($parent),
                'until' => null,
                'product' => $state['product'],
                'address' => self::address($parent),
            ];
        }

        if ($state['reason'] === CheckoutMode::REASON_CLOSED) {
            CheckoutMode::stop();

            return ['state' => 'closed', 'parent' => null, 'orderNumber' => null, 'until' => null, 'product' => null, 'address' => null];
        }

        return null;
    }

    /** "Dorpsstraat 12, 1234 AB Ons Dorp" */
    public static function address(Order $order): string
    {
        $street = trim($order->street.' '.$order->house_nr);
        $city = trim($order->zip_code.' '.$order->city);

        return implode(', ', array_filter([$street, $city]));
    }

    private static function offerOpen(Order $order, string $entryPoint): bool
    {
        return (bool) rescue(
            fn () => Settings::entryPointEnabled($entryPoint, $order->site_id) && Offer::isOpen($order),
            false,
            true,
        );
    }
}
