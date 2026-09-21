<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedCore\Mail\Concerns\HasEmailTemplate;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Mail\Concerns\AttachesInvoice;
use Dashed\DashedCore\Mail\Contracts\RegistersEmailTemplate;
use Dashed\DashedEcommerceCore\Services\OnAccount\OnAccountSettings;

class PaymentReminderMail extends Mailable implements RegistersEmailTemplate
{
    use AttachesInvoice;
    use HasEmailTemplate;
    use Queueable;
    use SerializesModels;

    public function __construct(public Order $order, public int $stage)
    {
    }

    public static function emailTemplateName(): string
    {
        return 'Betaalherinnering (klant)';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Herinnering voor een openstaande factuur op rekening. De tekst per stap staat bij Instellingen, Op rekening.';
    }

    public static function availableVariables(): array
    {
        return ['invoiceId', 'outstandingAmountFormatted', 'dueDate', 'daysOverdue', 'paymentUrl', 'customerFirstName', 'companyName', 'siteName', 'reminderBody', 'primaryColor'];
    }

    public static function defaultSubject(): string
    {
        return ':reminderSubject:';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'text', 'data' => ['body' => ':reminderBody:']],
            ['type' => 'button', 'data' => ['label' => 'Betaal factuur', 'url' => ':paymentUrl:', 'background' => ':primaryColor:', 'color' => '#ffffff']],
        ];
    }

    public static function sampleData(): array
    {
        $order = Order::query()->whereNotNull('payment_due_at')->latest()->first()
            ?? Order::query()->latest()->first();

        if (! $order) {
            return [
                'invoiceId' => 'DEMO-001',
                'outstandingAmountFormatted' => CurrencyHelper::formatPrice(99.95),
                'dueDate' => now()->subDays(3)->format('d-m-Y'),
                'daysOverdue' => 3,
                'paymentUrl' => url('/pay/demo'),
                'customerFirstName' => 'Jan',
                'companyName' => 'Demo BV',
                'siteName' => Customsetting::get('site_name'),
                'reminderSubject' => 'Herinnering: factuur DEMO-001',
                'reminderBody' => '<p>Beste Jan,</p><p>Wij hebben de betaling van factuur DEMO-001 nog niet ontvangen.</p>',
            ];
        }

        return (new self($order, 1))->context();
    }

    public static function makeForTest(): ?self
    {
        $order = Order::query()->whereNotNull('payment_due_at')->latest()->first()
            ?? Order::query()->latest()->first();

        return $order ? new self($order, 1) : null;
    }

    public function context(): array
    {
        $stages = OnAccountSettings::reminderStages($this->order->site_id, $this->order->locale);
        $stage = $stages[$this->stage] ?? ($stages === [] ? [] : $stages[array_key_last($stages)]);

        $values = [
            'invoiceId' => $this->order->invoice_id,
            'outstandingAmountFormatted' => CurrencyHelper::formatPrice($this->order->outstandingAmount()),
            'dueDate' => $this->order->payment_due_at?->format('d-m-Y') ?? '',
            'daysOverdue' => $this->order->payment_due_at ? max(0, (int) $this->order->payment_due_at->copy()->startOfDay()->diffInDays(now()->startOfDay())) : 0,
            // Op het domein van de site: de herinneringen gaan vanuit de
            // console de deur uit, en daar is route() gewoon APP_URL.
            'paymentUrl' => Sites::url($this->order->paymentUrl(), $this->order->site_id),
            'customerFirstName' => $this->order->first_name,
            'companyName' => $this->order->company_name,
            'siteName' => Customsetting::get('site_name', $this->order->site_id),
        ];

        $replace = fn (string $text, array $values) => str_replace(
            array_map(fn ($key) => ':'.$key.':', array_keys($values)),
            array_map('strval', array_values($values)),
            $text,
        );

        // De tekst van de stap is HTML; wat de klant zelf invulde (naam,
        // bedrijf) mag daar niet als opmaak of script in terechtkomen. Het
        // onderwerp is platte tekst en krijgt de waarden ongewijzigd.
        $htmlValues = array_merge($values, [
            'customerFirstName' => e((string) $values['customerFirstName']),
            'companyName' => e((string) $values['companyName']),
        ]);

        return array_merge($values, [
            'order' => $this->order,
            'reminderSubject' => $replace($stage['subject'] ?? '', $values),
            'reminderBody' => $replace($stage['body'] ?? '', $htmlValues),
        ]);
    }

    public function build()
    {
        $context = $this->context();
        $html = $this->renderFromTemplate($context, $this->order->locale)
            ?? $context['reminderBody'].'<p><a href="'.e($context['paymentUrl']).'">'.e(__('Betaal factuur')).'</a></p>';

        [$fromEmail, $fromName] = $this->templateFrom(Customsetting::get('site_from_email', $this->order->site_id), Customsetting::get('site_name', $this->order->site_id), $this->order->locale);

        $mail = $this->html($html)
            ->from($fromEmail, $fromName)
            ->subject($this->templateSubject($context['reminderSubject'], $context, $this->order->locale));

        return $mail->attachInvoiceFromDisk($this->order);
    }
}
