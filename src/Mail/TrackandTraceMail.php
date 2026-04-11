<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedCore\Mail\Concerns\HasEmailTemplate;
use Dashed\DashedEcommerceCore\Models\OrderTrackAndTrace;
use Dashed\DashedCore\Mail\Contracts\RegistersEmailTemplate;

class TrackandTraceMail extends Mailable implements RegistersEmailTemplate
{
    use HasEmailTemplate;
    use Queueable;
    use SerializesModels;

    public OrderTrackAndTrace $trackAndTrace;

    public function __construct(OrderTrackAndTrace $trackAndTrace)
    {
        $this->trackAndTrace = $trackAndTrace;
    }

    public static function emailTemplateName(): string
    {
        return 'Track & trace';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden naar de klant met track & trace informatie.';
    }

    public static function availableVariables(): array
    {
        return ['orderId', 'customerFirstName', 'trackAndTraceCode', 'trackAndTraceUrl', 'siteName', 'primaryColor'];
    }

    public static function defaultSubject(): string
    {
        return 'Er is een track & trace beschikbaar voor bestelling :orderId:';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Je bestelling is onderweg!', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>Beste :customerFirstName:,</p><p>Je bestelling #:orderId: is verzonden. Gebruik onderstaande link om je zending te volgen.</p>']],
            ['type' => 'button', 'data' => ['label' => 'Volg je zending', 'url' => ':trackAndTraceUrl:', 'background' => ':primaryColor:', 'color' => '#ffffff']],
            ['type' => 'divider', 'data' => []],
            ['type' => 'text', 'data' => ['body' => '<p>Met vriendelijke groet,<br>Het team van :siteName:</p>']],
        ];
    }

    public static function sampleData(): array
    {
        $tt = OrderTrackAndTrace::query()->latest()->first();
        $order = $tt?->order;

        return [
            'trackAndTrace' => $tt,
            'order' => $order,
            'orderId' => $order?->invoice_id ?? 'DEMO-001',
            'customerFirstName' => $order?->first_name ?? 'Jan',
            'trackAndTraceCode' => $tt?->code ?? 'ABC123',
            'trackAndTraceUrl' => $tt?->url ?? '#',
            'siteName' => Customsetting::get('site_name'),
        ];
    }

    public static function makeForTest(): ?self
    {
        $tt = OrderTrackAndTrace::query()->latest()->first();

        return $tt ? new self($tt) : null;
    }

    public function build()
    {
        $order = $this->trackAndTrace->order;
        $context = [
            'trackAndTrace' => $this->trackAndTrace,
            'order' => $order,
            'orderId' => $order?->invoice_id ?? '',
            'customerFirstName' => $order?->first_name ?? '',
            'trackAndTraceCode' => $this->trackAndTrace->code ?? '',
            'trackAndTraceUrl' => $this->trackAndTrace->url ?? '#',
            'siteName' => Customsetting::get('site_name'),
        ];

        $templateHtml = $this->renderFromTemplate($context);

        if ($templateHtml !== null) {
            $subject = $this->templateSubject(
                Translation::get('order-track-and-trace-email-subject', 'track-and-trace', 'Er is een track & trace beschikbaar voor bestelling :orderId:', 'text', [
                    'orderId' => $order?->invoice_id ?? '',
                ]),
                $context
            );
            [$fromEmail, $fromName] = $this->templateFrom(Customsetting::get('site_from_email'), Customsetting::get('site_name'));

            return $this->html($templateHtml)->from($fromEmail, $fromName)->subject($subject);
        }

        $view = view()->exists(config('dashed-core.site_theme', 'dashed') . '.emails.track-and-trace')
            ? config('dashed-core.site_theme', 'dashed') . '.emails.track-and-trace'
            : 'dashed-ecommerce-core::emails.track-and-trace';

        return $this->view($view)
            ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
            ->subject(Translation::get('order-track-and-trace-email-subject', 'track-and-trace', 'Er is een track & trace beschikbaar voor bestelling :orderId:', 'text', [
                'orderId' => $order?->invoice_id ?? '',
            ]))
            ->with([
                'trackAndTrace' => $this->trackAndTrace,
            ]);
    }
}
