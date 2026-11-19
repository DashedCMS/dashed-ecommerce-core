<?php

namespace Dashed\DashedEcommerceCore\Commands;

use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Services\ProductReviews\ReviewRequestSender;

class SendProductReviewRequestsCommand extends Command
{
    protected $signature = 'dashed:send-product-review-requests {--limit=200}';

    protected $description = 'Verstuur de reviewverzoeken waarvan de wachttijd na levering voorbij is';

    public function handle(): int
    {
        $result = ReviewRequestSender::sendDue((int) $this->option('limit'));

        $this->info("Verstuurd: {$result['sent']}, overgeslagen: {$result['skipped']}");

        return self::SUCCESS;
    }
}
