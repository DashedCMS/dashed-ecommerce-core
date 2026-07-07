<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Services;

use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Mail\BackInStockMail;
use Dashed\DashedEcommerceCore\Models\StockNotification;

class BackInStockService
{
    public function subscribe(string $siteId, int $productId, string $email): StockNotification
    {
        $email = mb_strtolower(trim($email));

        return StockNotification::firstOrCreate([
            'site_id' => $siteId,
            'product_id' => $productId,
            'email' => $email,
            'notified_at' => null,
        ]);
    }

    public function notifyPending(?string $siteId = null): int
    {
        $sent = 0;
        $query = StockNotification::pending()->with('product');
        if ($siteId !== null) {
            $query->where('site_id', $siteId);
        }

        foreach ($query->get()->groupBy('product_id') as $rows) {
            $product = $rows->first()->product;
            if (! $product || ! $product->hasDirectSellableStock()) {
                continue;
            }
            foreach ($rows as $row) {
                Mail::queue(new BackInStockMail($product, $row->email));
                $row->update(['notified_at' => now()]);
                $sent++;
            }
        }

        return $sent;
    }
}
