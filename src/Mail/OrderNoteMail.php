<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedCore\Mail\Concerns\HasEmailTemplate;
use Dashed\DashedCore\Mail\Contracts\RegistersEmailTemplate;

class OrderNoteMail extends Mailable implements RegistersEmailTemplate
{
    use HasEmailTemplate;
    use Queueable;
    use SerializesModels;

    public Order $order;

    public OrderLog $orderLog;

    public function __construct(Order $order, OrderLog $orderLog)
    {
        $this->order = $order;
        $this->orderLog = $orderLog;
    }

    public static function emailTemplateName(): string
    {
        return 'Notitie bij order';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden naar de klant als er een notitie is toegevoegd aan een order.';
    }

    public static function availableVariables(): array
    {
        return ['orderId', 'customerFirstName', 'noteContent', 'siteName', 'primaryColor'];
    }

    public static function defaultSubject(): string
    {
        return 'Update over je bestelling #:orderId:';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Update over je bestelling', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>Beste :customerFirstName:,</p><p>:noteContent:</p>']],
            ['type' => 'divider', 'data' => []],
            ['type' => 'order-details', 'data' => []],
        ];
    }

    public static function sampleData(): array
    {
        $orderLog = OrderLog::query()->latest()->first();
        $order = $orderLog?->order ?? Order::query()->latest()->first();

        return [
            'order' => $order,
            'orderLog' => $orderLog,
            'orderId' => $order?->invoice_id ?? 'DEMO-001',
            'customerFirstName' => $order?->first_name ?? 'Jan',
            'noteContent' => $orderLog?->email_content ?? 'Er is een notitie toegevoegd aan je bestelling.',
            'siteName' => Customsetting::get('site_name'),
        ];
    }

    public static function makeForTest(): ?self
    {
        $orderLog = OrderLog::query()->whereNotNull('email_content')->latest()->first() ?? OrderLog::query()->latest()->first();
        if (! $orderLog || ! $orderLog->order) {
            return null;
        }

        return new self($orderLog->order, $orderLog);
    }

    public function build()
    {
        $context = [
            'order' => $this->order,
            'orderLog' => $this->orderLog,
            'orderId' => $this->order->invoice_id,
            'customerFirstName' => $this->order->first_name,
            'noteContent' => $this->orderLog->email_content ?? '',
            'siteName' => Customsetting::get('site_name'),
        ];

        $fallbackSubject = $this->orderLog->email_subject ?: Translation::get('order-note-update-email-subject', 'orders', 'Update over je bestelling #:orderId:', 'text', [
            'orderId' => $this->order->invoice_id,
        ]);

        $templateHtml = $this->renderFromTemplate($context);

        if ($templateHtml !== null) {
            $subject = $this->templateSubject($fallbackSubject, $context);
            [$fromEmail, $fromName] = $this->templateFrom(Customsetting::get('site_from_email'), Customsetting::get('site_name'));
            $mail = $this->html($templateHtml)->from($fromEmail, $fromName)->subject($subject);
        } else {
            $view = view()->exists(config('dashed-core.site_theme', 'dashed') . '.emails.order-note')
                ? config('dashed-core.site_theme', 'dashed') . '.emails.order-note'
                : 'dashed-ecommerce-core::emails.order-note';

            $mail = $this->view($view)
                ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
                ->subject($fallbackSubject)
                ->with([
                    'order' => $this->order,
                    'orderLog' => $this->orderLog,
                    'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
                ]);
        }

        foreach ($this->orderLog->images ?: [] as $image) {
            $media = mediaHelper()->getSingleMedia($image);
            if ($media) {
                $mail->attachFromStorageDisk('dashed', $media->path);
            }
        }

        return $mail;
    }
}
