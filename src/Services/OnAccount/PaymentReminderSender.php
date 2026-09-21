<?php

namespace Dashed\DashedEcommerceCore\Services\OnAccount;

use Illuminate\Support\Facades\Mail;
use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Illuminate\Database\UniqueConstraintViolationException;
use Dashed\DashedEcommerceCore\Mail\PaymentReminderMail;
use Dashed\DashedEcommerceCore\Models\OrderPaymentReminder;

/**
 * Wie krijgt vandaag welke herinnering. Alleen de hoogste stap die aan de
 * beurt is: wie de functie aanzet op een oude achterstand stuurt één mail
 * per order, niet eerst nog de vriendelijke herinnering van weken terug.
 */
class PaymentReminderSender
{
    public static function dueStage(Order $order): ?int
    {
        if (! $order->payment_due_at) {
            return null;
        }

        $sent = self::sentStages($order);
        $due = null;

        foreach (OnAccountSettings::reminderStages($order->site_id, $order->locale) as $stage => $config) {
            if ($order->payment_due_at->copy()->addDays($config['days'])->lte(now())) {
                $due = $stage;
            }
        }

        if ($due === null || in_array($due, $sent, true)) {
            return null;
        }

        // Een hogere stap die al verstuurd is, maakt een lagere overbodig.
        if (! empty($sent) && max($sent) > $due) {
            return null;
        }

        return $due;
    }

    public static function send(Order $order, int $stage, ?User $by = null): bool
    {
        try {
            OrderPaymentReminder::create([
                'order_id' => $order->id,
                'stage' => $stage,
                'sent_at' => now(),
                'email' => $order->email,
                'sent_by_user_id' => $by?->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        Mail::to($order->email)->queue(new PaymentReminderMail($order, $stage));

        OrderLog::createLog(orderId: $order->id, tag: 'order.payment-reminder.sent', note: (string) $stage);

        return true;
    }

    public static function sendManual(Order $order, User $by): void
    {
        $stages = OnAccountSettings::reminderStages($order->site_id, $order->locale);
        $sent = self::sentStages($order);

        foreach (array_keys($stages) as $stage) {
            if (! in_array($stage, $sent, true)) {
                self::send($order, $stage, $by);

                return;
            }
        }

        $last = array_key_last($stages);
        if ($last === null) {
            return;
        }

        Mail::to($order->email)->queue(new PaymentReminderMail($order, $last));
        OrderLog::createLog(orderId: $order->id, tag: 'order.payment-reminder.manual', note: (string) $last);
    }

    public static function run(): int
    {
        $count = 0;

        Order::query()
            ->onAccountOpen()
            ->whereNull('payment_reminders_paused_at')
            ->where(fn ($q) => $q->whereNull('order_origin')->orWhere('order_origin', '!=', 'Bol'))
            ->where('payment_due_at', '<', now())
            ->with('paymentReminders')
            ->chunkById(200, function ($orders) use (&$count) {
                foreach ($orders as $order) {
                    $stage = self::dueStage($order);
                    if ($stage !== null && self::send($order, $stage)) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    /**
     * Al verstuurde stappen van deze order. Gebruikt de al ingeladen relatie
     * zodra die er is (run() eager-laadt hem voor de hele chunk), anders een
     * losse query: dat houdt sendManual() en losse aanroepen buiten een run()
     * net zo correct zonder de N+1 van run() terug te halen.
     */
    private static function sentStages(Order $order): array
    {
        return $order->relationLoaded('paymentReminders')
            ? $order->paymentReminders->pluck('stage')->all()
            : $order->paymentReminders()->pluck('stage')->all();
    }
}
