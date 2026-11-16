<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Support\BackgroundTasks;

/**
 * Lichte, gerichte prijs-herberekening: draait alleen Product::calculatePrices()
 * voor de opgegeven producten. Wordt gebruikt na een prijsgroep- of
 * prijs-per-gebruiker-wijziging zodat niet de volledige product-rebuild
 * (voorraad, filters, bundels, indexering) over de hele catalogus loopt.
 */
class RecalculateProductPricesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const TASK_TYPE = 'product-prices-recalculate';

    /**
     * Vanaf dit aantal producten meldt de job zich in het takenicoon.
     */
    public const TASK_MIN_PRODUCTS = 25;

    public $tries = 5;
    public $timeout = 1200;

    /**
     * De beheerder die de herberekening startte, voor het takenicoon. Met
     * een standaardwaarde, zodat een job die al in de wachtrij stond hem
     * ook heeft.
     */
    public ?int $userId = null;

    /**
     * @param  array<int>  $productIds
     */
    public function __construct(public array $productIds, ?int $userId = null)
    {
        $this->userId = $userId;
    }

    public function handle(): void
    {
        $ids = array_values(array_unique(array_filter($this->productIds)));
        if (! $ids) {
            return;
        }

        $task = count($ids) >= self::TASK_MIN_PRODUCTS
            ? BackgroundTasks::start(self::TASK_TYPE, __('Prijzen herberekenen'), count($ids), $this->userId, $this->job?->uuid())
            : null;
        $done = 0;

        Product::whereIn('id', $ids)->chunkById(100, function ($products) use ($task, &$done) {
            foreach ($products as $product) {
                DB::transaction(fn () => $product->calculatePrices(), 5);
                $done++;
            }

            $task?->progress($done);
        });

        $task?->finish(__(':aantal producten bijgewerkt.', ['aantal' => $done]));
    }

    public function failed(Throwable $e): void
    {
        BackgroundTasks::fail(self::TASK_TYPE, $e, $this->job?->uuid(), $this->userId);
    }
}
