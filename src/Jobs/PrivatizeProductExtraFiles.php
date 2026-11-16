<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Dashed\DashedEcommerceCore\Classes\ProductExtraFile;
use Dashed\DashedCore\Security\Uploads\PrivateUploads;

/**
 * Verhuist bestanden bij product-extra's in orderregels (dashed/product-extras
 * op de media-disk) naar de privé opslag van dashed-core en zet het nieuwe pad
 * in dashed__order_products.product_extras. Op een lokale media-disk stonden
 * ze via /storage gewoon open; op DigitalOcean als privé object, maar in
 * dezelfde bucket als de publieke media.
 *
 * Gestart door de migratie privatize_product_extra_files (alleen in
 * productie) of met `php artisan dashed-ecommerce:privatize-product-extra-files`.
 * Veilig om opnieuw te draaien.
 */
class PrivatizeProductExtraFiles implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    public int $tries = 3;

    public const BUDGET_SECONDS = 600;

    /**
     * Nooit twee runs tegelijk; een vervolgrun die de job zelf inplant komt
     * daardoor gewoon achter de lopende aan.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping(static::class))->releaseAfter(120)->expireAfter(1200)];
    }

    public function handle(): void
    {
        $started = microtime(true);
        $stats = ['rows' => 0, 'moved' => 0, 'missing' => 0];
        $done = true;
        $legacy = ProductExtraFile::DIRECTORY . '/';

        $query = DB::table('dashed__order_products')->where('product_extras', 'like', '%' . str_replace('/', '%', $legacy) . '%');

        foreach ($query->orderBy('id')->lazyById(200) as $row) {
            if (microtime(true) - $started > static::BUDGET_SECONDS) {
                $done = false;

                break;
            }

            $extras = json_decode((string) $row->product_extras, true);
            if (! is_array($extras)) {
                continue;
            }

            $changed = false;
            $movedFrom = [];
            foreach ($extras as $index => $extra) {
                $path = is_array($extra) ? (string) ($extra['path'] ?? '') : '';
                if (! str_starts_with($path, $legacy)) {
                    continue;
                }

                $target = PrivateUploads::moveIntoPrivate(ProductExtraFile::DISK, $path, 'product-extras', deleteSource: false);
                if (! $target) {
                    $stats['missing']++;

                    continue;
                }

                $extras[$index]['path'] = $target;
                $movedFrom[] = $path;
                $changed = true;
                $stats['moved']++;
            }

            if ($changed) {
                DB::table('dashed__order_products')->where('id', $row->id)->update(['product_extras' => json_encode($extras)]);
                $stats['rows']++;

                // Originelen pas weg na het bijwerken van de orderregel.
                // Een kopie van een order kan naar hetzelfde bestand wijzen: dan blijft het
                // origineel staan tot ook die orderregel is verwerkt.
                foreach ($movedFrom as $oldPath) {
                    $stillUsed = DB::table('dashed__order_products')
                        ->where('product_extras', 'like', '%' . basename($oldPath) . '%')
                        ->exists();
                    if (! $stillUsed) {
                        rescue(fn () => Storage::disk(ProductExtraFile::DISK)->delete($oldPath), null, false);
                    }
                }
            }
        }

        Log::info('[dashed-ecommerce] product-extra-bestanden naar privé opslag', $stats + ['klaar' => $done]);

        if (! $done) {
            static::dispatch()->delay(now()->addMinute());
        }
    }
}
