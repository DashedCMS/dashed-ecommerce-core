<?php

namespace Dashed\DashedEcommerceCore\Commands;

use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Models\DiscountCode;

class UpdateExpiredGlobalDiscountCodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dashed:update-expired-global-discount-codes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update expired global discount codes';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $globalDiscountCodes = DiscountCode::isGlobalDiscount()
            ->whereNotNull('end_date')
            ->where('end_date', '<', now())
            ->where('end_date', '>', now()->subMinutes(15))
            ->get();

        foreach ($globalDiscountCodes as $globalDiscountCode) {
            $globalDiscountCode->updateProductPrices();
        }
    }
}
