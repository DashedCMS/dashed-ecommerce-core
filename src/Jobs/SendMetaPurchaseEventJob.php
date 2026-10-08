<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Throwable;
use RuntimeException;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\MetaCapiEvent;
use Dashed\DashedEcommerceCore\Services\Meta\ConversionsApi;
use Dashed\DashedEcommerceCore\Services\Meta\MetaCapiSettings;

/**
 * Stuurt de Purchase van één order naar Meta's Conversions API. Idempotent op
 * event_id: een dubbele webhook, een tweede dispatch of een order die van
 * betaald af en weer terug gaat, levert geen tweede verzending op.
 */
class SendMetaPurchaseEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    // Horizon staat standaard op één poging; deze job regelt het zelf.
    public int $tries = 5;

    public int $timeout = 60;

    /** @var array<int,int> */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public int $orderId)
    {
    }

    public function handle(ConversionsApi $api): void
    {
        $order = Order::with(['orderProducts', 'orderPayments', 'tracking'])->find($this->orderId);
        if (! $order) {
            $this->skipPendingRowOfMissingOrder();

            return;
        }

        $settings = MetaCapiSettings::for($order->site_id);
        $eventId = ConversionsApi::eventId($order);
        $lock = Cache::lock('meta-capi.' . $eventId, 120);

        if (! $lock->get()) {
            // Een andere worker is met hetzelfde event bezig.
            $this->release(30);

            return;
        }

        try {
            $row = MetaCapiEvent::firstOrCreate(['event_id' => $eventId], [
                'site_id' => $order->site_id,
                'order_id' => $order->id,
                'event_name' => 'Purchase',
            ]);

            $testEventCode = $settings->testEventCode();

            // Verstuurd is klaar. Enige uitzondering: het event ging als test de
            // deur uit en de test event code is intussen leeggemaakt; dan heeft
            // Meta de aankoop nog niet echt gekregen.
            if ($row->status === MetaCapiEvent::STATUS_SENT && ! ($row->sentAsTest() && $testEventCode === null)) {
                return;
            }

            if ($reason = static::skipReason($order, $settings)) {
                $row->update(['status' => MetaCapiEvent::STATUS_SKIPPED, 'response' => ['reason' => $reason]]);

                return;
            }

            $event = $api->purchaseEvent($order);
            $result = $api->sendEvent($order->site_id, $event);

            $row->update([
                'payload' => $event,
                'attempts' => $row->attempts + 1,
                'response' => ['status' => $result['status'], 'body' => $result['body']]
                    + ($testEventCode !== null ? ['test' => true] : []),
                'status' => $result['ok'] ? MetaCapiEvent::STATUS_SENT : MetaCapiEvent::STATUS_FAILED,
                'sent_at' => $result['ok'] ? Carbon::now() : null,
            ]);
        } finally {
            $lock->release();
        }

        // Een storing of time-out kan vanzelf overgaan; een 4xx (token, afgekeurd
        // event) niet. Alleen het eerste geval gaat terug de wachtrij in.
        if (! $result['ok'] && ($result['status'] === 0 || $result['status'] >= 500)) {
            throw new RuntimeException("Meta Conversions API: event {$eventId} mislukt met status {$result['status']}");
        }
    }

    /**
     * Na de laatste poging, een time-out of een gecrashte worker: een rij die
     * nog op pending staat zou anders nooit meer van status veranderen.
     */
    public function failed(?Throwable $e): void
    {
        try {
            $row = MetaCapiEvent::where('event_id', ConversionsApi::eventIdForOrderId($this->orderId))
                ->where('status', MetaCapiEvent::STATUS_PENDING)
                ->first();

            $row?->update([
                'status' => MetaCapiEvent::STATUS_FAILED,
                'response' => ['error' => ConversionsApi::clean((string) $e?->getMessage())],
            ]);
        } catch (Throwable $inner) {
            report($inner);
        }
    }

    /** De order is (soft) verwijderd: een rij die op hem wachtte komt nooit meer aan de beurt. */
    protected function skipPendingRowOfMissingOrder(): void
    {
        MetaCapiEvent::where('order_id', $this->orderId)
            ->where('status', MetaCapiEvent::STATUS_PENDING)
            ->get()
            ->each(fn (MetaCapiEvent $row) => $row->update([
                'status' => MetaCapiEvent::STATUS_SKIPPED,
                'response' => ['reason' => 'order bestaat niet meer'],
            ]));
    }

    /** De reden waarom er voor deze order geen Purchase gaat, of null als hij mag. */
    public static function skipReason(Order $order, MetaCapiSettings $settings): ?string
    {
        if (! $settings->ready()) {
            return 'Conversions API staat uit of pixel-ID/token ontbreekt';
        }

        return static::orderSkipReason($order);
    }

    /** Alleen de regels die over de order gaan, los van de instellingen. */
    public static function orderSkipReason(Order $order): ?string
    {
        return match (true) {
            $order->status !== 'paid' => 'order is niet betaald',
            ! $order->tracking => 'geen klantsignalen: order komt niet uit de webshop-checkout',
            ! $order->tracking->marketing_consent => 'geen marketingtoestemming',
            (bool) $order->credit_for_order_id => 'creditorder',
            $order->replacesOrder()->exists() => 'vervangt een eerdere order',
            (float) $order->total <= 0 => 'orderbedrag is niet positief',
            ConversionsApi::paymentTooOld($order) => 'betaling is ouder dan zes dagen',
            default => null,
        };
    }
}
