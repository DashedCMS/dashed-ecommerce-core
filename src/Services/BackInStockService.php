<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Services;

use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Mail\BackInStockMail;
use Dashed\DashedEcommerceCore\Models\StockNotification;

class BackInStockService
{
    public function subscribe(string $siteId, int $productId, string $email, ?string $locale = null): StockNotification
    {
        $email = mb_strtolower(trim($email));

        $notification = StockNotification::firstOrCreate([
            'site_id' => $siteId,
            'product_id' => $productId,
            'email' => $email,
            'notified_at' => null,
        ], [
            'locale' => $locale ?: app()->getLocale(),
        ]);

        // Opnieuw aangemeld in een andere taal: de laatste keuze telt.
        if (! $notification->wasRecentlyCreated && $locale && $notification->locale !== $locale) {
            $notification->update(['locale' => $locale]);
        }

        return $notification;
    }

    /**
     * Mailt iedereen die wacht op een product dat weer direct leverbaar is.
     *
     * Elke rij wordt eerst atomair geclaimd (notified_at alleen zetten als
     * die nog leeg is), zodat twee runs die elkaar overlappen nooit twee
     * mails voor dezelfde aanmelding sturen. Een product dat niet meer
     * publiek is blijft wachten: een mail met een link naar een 404 helpt
     * niemand.
     */
    public function notifyPending(?string $siteId = null): int
    {
        $sent = 0;
        $query = StockNotification::pending()->with('product');
        if ($siteId !== null) {
            $query->where('site_id', $siteId);
        }

        foreach ($query->get()->groupBy('product_id') as $rows) {
            $product = $rows->first()->product;
            if (! $product || ! $product->public || ! $product->hasDirectSellableStock()) {
                continue;
            }

            $mailed = [];

            foreach ($rows as $row) {
                $claimed = StockNotification::query()
                    ->whereKey($row->id)
                    ->whereNull('notified_at')
                    ->update(['notified_at' => now()]) === 1;

                if (! $claimed) {
                    continue;
                }

                // Twee aanmeldingen van hetzelfde adres (bijvoorbeeld via de
                // chat en via de productpagina) geven één mail.
                $key = $row->email . '|' . $row->site_id;
                if (isset($mailed[$key])) {
                    continue;
                }
                $mailed[$key] = true;

                $locale = $row->locale ?: null;
                $mail = new BackInStockMail($product, $row->email, $locale);
                if ($locale) {
                    $mail->locale($locale);
                }

                Mail::queue($mail);
                $sent++;
            }
        }

        return $sent;
    }
}
