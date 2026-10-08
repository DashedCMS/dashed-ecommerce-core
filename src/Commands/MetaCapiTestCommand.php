<?php

namespace Dashed\DashedEcommerceCore\Commands;

use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Services\Meta\ConversionsApi;
use Dashed\DashedEcommerceCore\Services\Meta\MetaCapiSettings;

class MetaCapiTestCommand extends Command
{
    protected $signature = 'meta:capi-test {order : Het id van de order}';

    protected $description = 'Stuur het Purchase-event van één order als testevent naar Meta en toon de response';

    public function handle(ConversionsApi $api): int
    {
        $order = Order::with(['orderProducts', 'orderPayments', 'tracking'])->find($this->argument('order'));
        if (! $order) {
            $this->error("Order {$this->argument('order')} niet gevonden.");

            return self::FAILURE;
        }

        $settings = MetaCapiSettings::for($order->site_id);

        if ($settings->testEventCode() === null) {
            $this->error('Vul eerst een test event code in bij Instellingen → Meta Conversions API. Zonder die code zou dit een echte aankoop melden.');

            return self::FAILURE;
        }

        if ($settings->pixelId() === null || ! $settings->hasAccessToken()) {
            $this->error('Pixel-ID of toegangstoken ontbreekt voor deze site.');

            return self::FAILURE;
        }

        // Het token zit niet in het event: sendEvent() voegt het pas in de body toe.
        $event = $api->purchaseEvent($order);

        $this->info("Pixel {$settings->pixelId()}, Graph {$settings->graphVersion()}, test event code {$settings->testEventCode()}");
        $this->line(json_encode($event, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $result = $api->sendEvent($order->site_id, $event);

        $this->line("HTTP {$result['status']}");
        $this->line(json_encode($result['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        if (! $result['ok']) {
            $this->error('Meta heeft het event niet geaccepteerd.');

            return self::FAILURE;
        }

        $this->info('Verstuurd. Controleer het tabblad Test events in Events Manager.');

        return self::SUCCESS;
    }
}
