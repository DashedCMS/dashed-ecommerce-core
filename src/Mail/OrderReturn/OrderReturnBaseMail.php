<?php

namespace Dashed\DashedEcommerceCore\Mail\OrderReturn;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedCore\Mail\Concerns\HasEmailTemplate;
use Dashed\DashedCore\Mail\Contracts\RegistersEmailTemplate;
use Dashed\DashedEcommerceCore\Classes\OrderVariableReplacer;

/**
 * Basis voor de retour-lifecycle mailables. Elke concrete subklasse stelt
 * zijn eigen emailTemplateName() en defaultBlocks() in en krijgt automatisch
 * een aparte EmailTemplate-rij in het admin-panel.
 */
abstract class OrderReturnBaseMail extends Mailable implements RegistersEmailTemplate, ShouldQueue
{
    use HasEmailTemplate;
    use Queueable;
    use SerializesModels;

    public function __construct(public OrderReturn $orderReturn)
    {
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
            'siteName',
            'primaryColor',
            'returnRequestedAt',
            'returnReason',
            'rejectedReason',
            'adminNote',
            'orderNumber',
            'returnLines',
        ];
    }

    public static function defaultSubject(): string
    {
        return 'Je retour voor bestelling :orderNumber:';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Je retour', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>Beste :firstName:,</p><p>We hebben je retourverzoek voor bestelling :orderNumber: ontvangen op :returnRequestedAt:.</p>']],
            ['type' => 'divider', 'data' => []],
            ['type' => 'order-details', 'data' => []],
            ['type' => 'divider', 'data' => []],
            ['type' => 'text', 'data' => ['body' => '<p>Met vriendelijke groet,<br>Het team van :siteName:</p>']],
        ];
    }

    public static function sampleData(): array
    {
        $return = OrderReturn::query()->latest()->first();

        return [
            'order' => $return?->order,
            'orderReturn' => $return,
            'siteName' => Customsetting::get('site_name'),
        ];
    }

    public static function makeForTest(): ?self
    {
        $return = OrderReturn::query()->latest()->first();

        return $return ? new static($return) : null;
    }

    public function build()
    {
        $order = $this->orderReturn->order;
        $locale = $order?->locale;

        $context = [
            'order' => $order,
            'orderReturn' => $this->orderReturn,
            'siteName' => Customsetting::get('site_name'),
        ];

        $templateHtml = $this->renderFromTemplate($context, $locale);

        if ($templateHtml !== null) {
            $template = \Dashed\DashedCore\Models\EmailTemplate::forMailable(static::emailTemplateKey());
            $rawSubject = $template?->getTranslation('subject', $locale, useFallbackLocale: true) ?: static::defaultSubject();

            $html = $order ? OrderVariableReplacer::handle($order, $templateHtml, true) : $templateHtml;
            $html = $this->replaceReturnVariables($html, true);

            $subject = $order ? OrderVariableReplacer::handle($order, (string) $rawSubject) : (string) $rawSubject;
            $subject = $this->replaceReturnVariables($subject);

            [$fromEmail, $fromName] = $this->templateFrom(
                Customsetting::get('site_from_email'),
                Customsetting::get('site_name'),
                $locale,
            );

            return $this->html($html)
                ->from($fromEmail, $fromName)
                ->subject($subject);
        }

        $view = view()->exists(config('dashed-core.site_theme', 'dashed') . '.emails.notification')
            ? config('dashed-core.site_theme', 'dashed') . '.emails.notification'
            : 'dashed-core::emails.notification';

        $defaultSubject = $order
            ? OrderVariableReplacer::handle($order, static::defaultSubject())
            : static::defaultSubject();
        $defaultSubject = $this->replaceReturnVariables($defaultSubject);

        return $this->view($view)
            ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
            ->subject($defaultSubject)
            ->with([
                'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
                'notification' => 'We hebben een update over je retourverzoek.',
            ]);
    }

    // :returnReason: maps to customer_note; :rejectedReason: to rejected_reason; :adminNote: to admin_note.
    // Free-text fields must be HTML-escaped when substituting into an email body.
    protected function replaceReturnVariables(string $value, bool $escapeHtml = false): string
    {
        $requestedAt = optional($this->orderReturn->requested_at)->format('d-m-Y H:i') ?? '';
        $reason = (string) $this->orderReturn->customer_note;
        $rejectedReason = (string) $this->orderReturn->rejected_reason;
        $adminNote = (string) $this->orderReturn->admin_note;
        $orderNumber = $this->orderReturn->order?->invoice_id ?: ('#' . ($this->orderReturn->order_id ?? ''));

        if ($escapeHtml) {
            $requestedAt = e($requestedAt);
            $reason = e($reason);
            $rejectedReason = e($rejectedReason);
            $adminNote = e($adminNote);
            $orderNumber = e($orderNumber);
        }

        $linesSummary = $this->orderReturn->lines
            ->map(function ($line) use ($escapeHtml) {
                $name = $line->orderProduct?->name ?? '';
                $part = $line->quantity . 'x ' . $name;

                $reasonLabel = $line->returnReason
                    ? $line->returnReason->getTranslation('label', app()->getLocale())
                    : null;
                if (filled($reasonLabel)) {
                    $part .= ' (' . $reasonLabel . ')';
                }
                if (filled($line->reason_note)) {
                    $part .= ' - ' . $line->reason_note;
                }

                return $escapeHtml ? e($part) : $part;
            })
            ->implode($escapeHtml ? '<br>' : ', ');

        return str_replace(
            [':returnRequestedAt:', ':returnReason:', ':rejectedReason:', ':adminNote:', ':orderNumber:', ':returnLines:'],
            [$requestedAt, $reason, $rejectedReason, $adminNote, $orderNumber, $linesSummary],
            $value
        );
    }
}
