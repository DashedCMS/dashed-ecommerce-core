<?php

namespace Dashed\DashedEcommerceCore\Mail\FulfillmentStatus;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Classes\Orders;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedCore\Mail\Concerns\HasEmailTemplate;
use Dashed\DashedCore\Mail\Contracts\RegistersEmailTemplate;
use Dashed\DashedEcommerceCore\Classes\OrderVariableReplacer;

/**
 * Basis voor de per-status fulfillment-notificatie mails. Elke concrete
 * subklasse stelt zijn eigen fulfillmentStatusKey() in en krijgt automatisch
 * een aparte EmailTemplate-rij (mailable_key = FQN van de subklasse).
 */
abstract class FulfillmentStatusChangedBaseMail extends Mailable implements RegistersEmailTemplate
{
    use HasEmailTemplate;
    use Queueable;
    use SerializesModels;

    public function __construct(public Order $order)
    {
    }

    /**
     * De fulfillment-status (key uit Orders::getFulfillmentStatusses()) waar
     * deze mail bij hoort.
     */
    abstract public static function fulfillmentStatusKey(): string;

    public static function emailTemplateName(): string
    {
        $key = static::fulfillmentStatusKey();
        $name = Orders::getFulfillmentStatusses()[$key] ?? $key;

        return 'Fulfillment status: ' . $name;
    }

    public static function emailTemplateDescription(): ?string
    {
        $key = static::fulfillmentStatusKey();
        $name = Orders::getFulfillmentStatusses()[$key] ?? $key;

        return 'Verzonden naar de klant wanneer de fulfillment status wijzigt naar "' . $name . '".';
    }

    public static function availableVariables(): array
    {
        return [
            'name',
            'firstName',
            'lastName',
            'email',
            'phoneNumber',
            'street',
            'houseNr',
            'zipCode',
            'city',
            'country',
            'companyName',
            'total',
            'tax',
            'amountOfProducts',
            'invoiceId',
            'orderId',
            'discount',
            'orderOrigin',
            'fulfillmentStatus',
            'fulfillmentStatusName',
            'trackingCodes',
            'trackingLinks',
            'siteName',
            'primaryColor',
        ];
    }

    public static function defaultSubject(): string
    {
        return 'Update over je bestelling :invoiceId:';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Update over je bestelling', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>Beste :firstName:,</p><p>De status van je bestelling :invoiceId: is gewijzigd naar <strong>:fulfillmentStatusName:</strong>.</p>']],
            ['type' => 'divider', 'data' => []],
            ['type' => 'order-track-and-trace', 'data' => []],
            ['type' => 'order-details', 'data' => []],
            ['type' => 'divider', 'data' => []],
            ['type' => 'text', 'data' => ['body' => '<p>Met vriendelijke groet,<br>Het team van :siteName:</p>']],
        ];
    }

    public static function sampleData(): array
    {
        $order = Order::query()->latest()->first();

        return [
            'order' => $order,
            'siteName' => Customsetting::get('site_name'),
        ];
    }

    public static function makeForTest(): ?self
    {
        $order = Order::query()->latest()->first();

        return $order ? new static($order) : null;
    }

    public function build()
    {
        $context = [
            'order' => $this->order,
            'siteName' => Customsetting::get('site_name'),
        ];

        $templateHtml = $this->renderFromTemplate($context, $this->order->locale);

        if ($templateHtml !== null) {
            $template = \Dashed\DashedCore\Models\EmailTemplate::forMailable(static::emailTemplateKey());
            $rawSubject = $template?->getTranslation('subject', $this->order->locale, useFallbackLocale: true) ?: static::defaultSubject();
            $subject = OrderVariableReplacer::handle($this->order, (string) $rawSubject);

            [$fromEmail, $fromName] = $this->templateFrom(
                Customsetting::get('site_from_email'),
                Customsetting::get('site_name'),
                $this->order->locale,
            );

            return $this->html(OrderVariableReplacer::handle($this->order, $templateHtml))
                ->from($fromEmail, $fromName)
                ->subject($subject);
        }

        $view = view()->exists(config('dashed-core.site_theme', 'dashed') . '.emails.notification')
            ? config('dashed-core.site_theme', 'dashed') . '.emails.notification'
            : 'dashed-core::emails.notification';

        return $this->view($view)
            ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
            ->subject(OrderVariableReplacer::handle($this->order, static::defaultSubject()))
            ->with([
                'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
                'notification' => 'De status van je bestelling is gewijzigd naar ' . (Orders::getFulfillmentStatusses()[static::fulfillmentStatusKey()] ?? static::fulfillmentStatusKey()) . '.',
            ]);
    }
}
