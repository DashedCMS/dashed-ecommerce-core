<?php

namespace Dashed\DashedEcommerceCore\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Dashed\DashedEcommerceCore\Jobs\RecalculateProductPricesJob;

/**
 * Eenmalige opschoning: gebruikers met een prijsgroep horen hun prijzen
 * volledig uit die groep te krijgen. Achtergebleven persoonlijke
 * product_user/product_category_user-regels schaduwen anders de groepsprijs
 * (zie Product::priceForUser). Dit commando verwijdert die regels voor
 * groep-gebruikers en herberekent de betrokken producten.
 */
class CleanupPriceGroupUserPricingCommand extends Command
{
    protected $signature = 'dashed:cleanup-price-group-user-pricing';

    protected $description = 'Verwijder achtergebleven persoonlijke prijzen van gebruikers die in een prijsgroep zitten';

    public function handle(): int
    {
        $affectedProductIds = [];
        $userCount = 0;

        User::whereNotNull('price_group_id')->chunkById(200, function ($users) use (&$affectedProductIds, &$userCount) {
            foreach ($users as $user) {
                $ids = DB::table('dashed__product_user')
                    ->where('user_id', $user->id)
                    ->pluck('product_id')
                    ->all();

                if (! $ids) {
                    DB::table('dashed__product_category_user')->where('user_id', $user->id)->delete();

                    continue;
                }

                DB::table('dashed__product_user')->where('user_id', $user->id)->delete();
                DB::table('dashed__product_category_user')->where('user_id', $user->id)->delete();

                $affectedProductIds = array_merge($affectedProductIds, $ids);
                $userCount++;
            }
        });

        $affectedProductIds = array_values(array_unique($affectedProductIds));

        if ($affectedProductIds) {
            foreach (array_chunk($affectedProductIds, 500) as $chunk) {
                RecalculateProductPricesJob::dispatch($chunk)->onQueue('ecommerce');
            }
        }

        $this->info("Opgeschoond: {$userCount} gebruikers, " . count($affectedProductIds) . ' producten ter herberekening.');

        return self::SUCCESS;
    }
}
