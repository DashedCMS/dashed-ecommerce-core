<?php

namespace Dashed\DashedEcommerceCore\Commands;

use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;

/**
 * Koppelt bestaande paid orders met abandoned_cart_recovery=true aan een
 * AbandonedCartEmail-record waarvoor converted_at nog leeg is. Nodig
 * omdat oudere mails de email_id niet in de resumeUrl meegaven, waardoor
 * Checkout::placeOrder geen koppeling kon leggen. Pakt de meest recente
 * verzonden mail vóór de order-aanmaak op het zelfde email-adres.
 */
class BackfillAbandonedCartConversions extends Command
{
    protected $signature = 'dashed:backfill-abandoned-cart-conversions
        {--dry-run : Toon wat er zou worden bijgewerkt, zonder schrijven}
        {--days=90 : Hoe ver in het verleden orders inspecteren}';

    protected $description = 'Vul converted_at + order_id in op AbandonedCartEmail-records voor reeds gerecoverde orders zonder email-koppeling.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $days = max(1, (int) $this->option('days'));

        // Orders met recovery-flag die nog geen AbandonedCartEmail-record
        // gekoppeld hebben via order_id. AbandonedCartEmail.order_id is de
        // canonieke koppeling die Checkout::placeOrder normaal zet via het
        // session-veld. Order-model heeft geen relatie naar deze tabel,
        // dus we lossen het op met een NOT EXISTS.
        $orders = Order::query()
            ->where('abandoned_cart_recovery', true)
            ->where('created_at', '>=', now()->subDays($days))
            ->whereNotNull('email')
            ->whereNotExists(function ($q) {
                $q->select('id')
                    ->from('dashed__abandoned_cart_emails')
                    ->whereColumn('dashed__abandoned_cart_emails.order_id', 'dashed__orders.id');
            })
            ->orderBy('created_at')
            ->get(['id', 'email', 'created_at', 'invoice_id']);

        if ($orders->isEmpty()) {
            $this->info('Geen orders zonder gekoppelde mail in laatste ' . $days . ' dagen.');

            return self::SUCCESS;
        }

        $linked = 0;
        $skipped = 0;

        foreach ($orders as $order) {
            $email = AbandonedCartEmail::query()
                ->where('email', $order->email)
                ->whereNotNull('sent_at')
                ->whereNull('converted_at')
                ->whereNull('order_id')
                ->where('sent_at', '<', $order->created_at)
                ->orderByDesc('sent_at')
                ->first();

            if (! $email) {
                $skipped++;
                $this->line(sprintf('  skip order #%s (%s) - geen passende mail', $order->invoice_id ?? $order->id, $order->email));

                continue;
            }

            $this->line(sprintf(
                '  link order #%s (%s) -> AbandonedCartEmail %d (sent_at %s)',
                $order->invoice_id ?? $order->id,
                $order->email,
                $email->id,
                $email->sent_at?->toDateTimeString() ?? '?',
            ));

            if (! $dryRun) {
                $email->update([
                    'order_id' => $order->id,
                    'converted_at' => $order->created_at,
                ]);
            }

            $linked++;
        }

        $this->info(($dryRun ? '[dry-run] ' : '') . "Gekoppeld: {$linked}, overgeslagen: {$skipped}.");

        return self::SUCCESS;
    }
}
