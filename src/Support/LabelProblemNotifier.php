<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Support;

use Throwable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Services\Shipping\LabelProblems\CarrierErrorParser;
use Dashed\DashedEcommerceCore\Services\Shipping\LabelProblems\LabelProblemDetector;

/**
 * Best-effort app-push als een verzendlabel (Veloyd/MyParcel) mislukt. Doet
 * niets zonder mobile-api; fouten worden gerapporteerd, nooit doorgegooid.
 * Per label en per afwijkende foutmelding hoogstens één push per dag.
 */
class LabelProblemNotifier
{
    private const CENTER = \Dashed\DashedMobileApi\Support\NotificationCenter::class;

    /** Aangeroepen vanuit de saved-hook van een vervoerder-labelrecord. */
    public static function fromLabel(Model $label): void
    {
        try {
            $error = trim((string) $label->error);

            if (! class_exists(self::CENTER) || $error === '' || $label->is_return) {
                return;
            }

            if (! $label->wasRecentlyCreated && ! $label->wasChanged('error')) {
                return;
            }

            $key = 'label-failed-push:' . $label->getTable() . ':' . $label->getKey() . ':' . md5($error);
            if (! Cache::add($key, true, now()->addDay())) {
                return;
            }

            $order = Order::find($label->order_id);
            if (! $order) {
                return;
            }

            self::labelFailed($order, LabelProblemDetector::cause(CarrierErrorParser::parse($error), $order));
        } catch (Throwable $e) {
            report($e);
        }
    }

    public static function labelFailed(Order $order, string $cause): void
    {
        if (! class_exists(self::CENTER)) {
            return;
        }

        try {
            app(self::CENTER)->push()
                ->type('order.label_failed')
                ->site($order->site_id)
                ->title(__('Label mislukt voor order :order', ['order' => $order->invoice_id ?: '#' . $order->id]))
                ->body($cause)
                ->route("/order/{$order->id}")
                ->data(['type' => 'order', 'id' => $order->id])
                ->toAbility('orders.read')
                ->send();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
