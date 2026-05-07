<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\URL;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderFlowEnrollment;
use Dashed\DashedEcommerceCore\Models\OrderHandledFlowStep;

/**
 * Render-mail voor de Order-handled-flow. Volgt exact dezelfde renderpipeline
 * als PopupFollowUpMail in dashed-popups: blokken (heading, paragraph, button,
 * image, divider, usp, discount) worden naar tabel-rijen vertaald binnen het
 * gedeelde dashed-core::emails.layout. Alle teksten + URLs ondersteunen
 * variabele-substitutie via :name: tokens.
 */
class OrderHandledMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public ?string $previewDiscountCode = null;

    public ?string $previewDiscountValue = null;

    public function __construct(
        public readonly Order $order,
        public readonly OrderHandledFlowStep $flowStep,
        ?string $locale = null,
    ) {
        if ($locale !== null) {
            $this->locale = $locale;
        }
    }

    public function build(): static
    {
        $locale = $this->locale ?? $this->order->locale ?? app()->getLocale();

        $siteName = $this->resolveSiteName();
        $siteUrl = (string) ($this->customsettingGet('site_url') ?: config('app.url') ?: '');
        $firstName = (string) ($this->order->first_name ?: '');
        $customerName = trim($firstName.' '.(string) ($this->order->last_name ?: ''));
        $orderNumber = (string) ($this->order->invoice_id ?: $this->order->id);
        // Lees de review-URL bij voorkeur uit de inschrijving zodat alle stappen
        // van de flow voor dezelfde klant dezelfde (gewogen-gekozen) URL gebruiken.
        // Valt terug op een verse weighted draw - die kent zelf weer een fallback
        // op de globale Customsetting 'order_handled_flow_review_url'.
        $reviewUrl = '';
        $enrollment = null;
        if ($this->order->id && $this->flowStep->flow_id) {
            $enrollment = OrderFlowEnrollment::query()
                ->where('order_id', $this->order->id)
                ->where('flow_id', $this->flowStep->flow_id)
                ->whereNull('cancelled_at')
                ->latest('id')
                ->first();
            $reviewUrl = (string) ($enrollment?->chosen_review_url ?? '');
        }

        if ($reviewUrl === '') {
            $picked = $this->flowStep->flow?->pickReviewUrl();
            $reviewUrl = (string) ($picked['url'] ?? '');

            // Backfill: enrollment bestond maar had nog geen chosen_review_url
            // (typisch voor inschrijvingen aangemaakt vóór v4.16.0, of als de
            // flow op dat moment nog geen review_urls had). Persisten zodra we
            // er via de weighted draw alsnog een vinden, zodat alle volgende
            // stappen van deze flow voor dezelfde klant op dezelfde URL
            // landen (consistent A/B-meten).
            if ($reviewUrl !== '' && $enrollment) {
                $enrollment->forceFill([
                    'chosen_review_url' => $reviewUrl,
                    'chosen_review_url_label' => $picked['label'] ?? null,
                ])->save();
            }
        }

        // Laatste vangnet: als er nergens een review-URL bekend is (geen
        // flow review_urls + geen Customsetting + geen enrollment) val
        // terug op de site-URL zodat de knop in de mail nooit leeg blijft.
        if ($reviewUrl === '') {
            $reviewUrl = $siteUrl;
        }

        $discountCode = $this->previewDiscountCode ?? '';
        $discountValue = $this->previewDiscountValue ?? '';

        $variables = [
            ':siteName:' => $siteName,
            ':siteUrl:' => $siteUrl,
            ':orderNumber:' => $orderNumber,
            ':customerName:' => $customerName,
            ':firstName:' => $firstName,
            ':discountCode:' => $discountCode,
            ':discountValue:' => $discountValue,
            ':reviewUrl:' => $reviewUrl,
        ];

        $subject = (string) $this->flowStep->getTranslation('subject', $locale, false);
        $subject = strtr($subject, $variables);
        if ($subject === '') {
            $subject = $siteName;
        }

        $rawBlocks = $this->flowStep->getTranslation('blocks', $locale, false) ?? [];
        if (! is_array($rawBlocks)) {
            $rawBlocks = [];
        }

        $primaryColor = $this->customsettingGet('mail_primary_color') ?: '#A0131C';
        $textColor = $this->customsettingGet('mail_text_color', '#ffffff');
        $backgroundColor = $this->customsettingGet('mail_background_color', '#f3f4f6');
        $footerText = $this->customsettingGet('mail_footer_text');

        $renderedBlocks = [];
        foreach ($rawBlocks as $block) {
            if (! is_array($block)) {
                continue;
            }
            $rendered = $this->renderBlock($block, $variables, $discountCode, $discountValue, $primaryColor, $textColor);
            if ($rendered !== null && $rendered !== '') {
                $renderedBlocks[] = $rendered;
            }
        }

        $showLogo = (bool) $this->customsettingGet('mail_show_logo', 1);
        $showSiteName = (bool) $this->customsettingGet('mail_show_site_name', 1);
        $siteLogo = null;
        if ($showLogo && function_exists('mediaHelper')) {
            $logoId = $this->customsettingGet('mail_logo') ?: $this->customsettingGet('site_logo');
            if ($logoId) {
                $media = mediaHelper()->getSingleMedia($logoId);
                $siteLogo = $media->url ?? null;
            }
        }

        $unsubscribeUrl = null;
        if ($this->order->id) {
            try {
                $unsubscribeUrl = URL::signedRoute(
                    'dashed.frontend.order-handled.unsubscribe',
                    ['order' => $this->order->id],
                );
            } catch (\Throwable $e) {
                $unsubscribeUrl = null;
            }
        }

        $mail = $this
            ->subject($subject)
            ->view('dashed-core::emails.layout')
            ->with([
                'blocks' => $renderedBlocks,
                'siteName' => $siteName,
                'siteLogo' => $siteLogo,
                'siteUrl' => $siteUrl,
                'showSiteName' => $showSiteName,
                'primaryColor' => $primaryColor,
                'textColor' => $textColor,
                'backgroundColor' => $backgroundColor,
                'footerText' => $footerText,
                'unsubscribeUrl' => $unsubscribeUrl,
                'unsubscribeLabel' => 'Afmelden',
            ]);

        $fromEmail = $this->resolveFromEmail();
        if ($fromEmail) {
            $mail->from($fromEmail, $siteName);
        }

        return $mail;
    }

    protected function renderBlock(
        array $block,
        array $variables,
        string $discountCode,
        string $discountValue,
        string $primaryColor,
        string $textColor,
    ): ?string {
        $type = $block['type'] ?? null;
        $data = is_array($block['data'] ?? null) ? $block['data'] : [];
        $sub = fn ($v) => is_string($v) ? strtr($v, $variables) : $v;

        switch ($type) {
            case 'heading':
                $text = $sub($data['text'] ?? $data['content'] ?? '');
                if ($text === '') {
                    return null;
                }

                return view('dashed-core::emails.blocks.heading', [
                    'text' => $text,
                    'level' => $data['level'] ?? 'h2',
                ])->render();

            case 'paragraph':
            case 'text':
                $body = $sub($data['body'] ?? $data['content'] ?? '');
                if ($body === '') {
                    return null;
                }

                return view('dashed-core::emails.blocks.text', ['body' => $body])->render();

            case 'button':
                $rawUrl = (string) $sub($data['url'] ?? '#');
                $url = $this->wrapTrackedUrl($rawUrl, 'button');

                return view('dashed-core::emails.blocks.button', [
                    'label' => $sub($data['label'] ?? 'Bekijk'),
                    'url' => $url,
                    'background' => $primaryColor,
                    'color' => $textColor,
                ])->render();

            case 'image':
                $src = $sub($data['src'] ?? $data['url'] ?? '');
                if ($src === '') {
                    return null;
                }
                $linkRaw = (string) $sub($data['link'] ?? '');
                $link = $linkRaw !== '' ? $this->wrapTrackedUrl($linkRaw, 'image') : '';

                return view('dashed-core::emails.blocks.image', [
                    'src' => $src,
                    'alt' => $sub($data['alt'] ?? ''),
                    'url' => $link,
                ])->render();

            case 'divider':
                return view('dashed-core::emails.blocks.divider')->render();

            case 'usp':
                $items = collect(explode("\n", (string) ($data['items'] ?? '')))
                    ->map(fn ($i) => trim((string) $i))
                    ->filter()
                    ->map($sub)
                    ->all();
                if (! $items) {
                    return null;
                }
                $list = '';
                foreach ($items as $item) {
                    $list .= '<li>'.htmlspecialchars($item, ENT_QUOTES, 'UTF-8').'</li>';
                }

                return '<tr><td style="padding: 8px 24px;"><ul style="margin:0; padding-left:20px; font-family: Arial, sans-serif; font-size:14px; line-height:1.8; color:#374151;">'.$list.'</ul></td></tr>';

            case 'discount':
                $label = $sub($data['label'] ?? 'Gebruik deze code voor extra korting:');
                $code = trim((string) $sub($data['code'] ?? '')) ?: $discountCode;
                if ($code === '') {
                    return null;
                }

                $valueRow = '';
                if ($discountValue !== '') {
                    $valueRow = '<div style="font-family: Arial, sans-serif; font-size:14px; color:#374151; margin-top:10px;">Bespaar <strong>'
                        .htmlspecialchars($discountValue, ENT_QUOTES, 'UTF-8')
                        .'</strong> op je bestelling</div>';
                }

                return '<tr><td align="center" style="padding: 16px 24px;">'
                    .'<div style="font-family: Arial, sans-serif; font-size:14px; color:#374151; margin-bottom:8px;">'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</div>'
                    .'<div style="display:inline-block; padding:12px 24px; background:'.$primaryColor.'; color:'.$textColor.'; font-family: Arial, sans-serif; font-size:18px; font-weight:bold; letter-spacing:1px; border-radius:6px;">'.htmlspecialchars($code, ENT_QUOTES, 'UTF-8').'</div>'
                    .$valueRow
                    .'</td></tr>';

            case 'order_products':
                $heading = trim((string) $sub($data['heading'] ?? 'Wat je hebt besteld:'));
                $orderProducts = $this->order
                    ->orderProducts()
                    ->whereNotNull('product_id')
                    ->orderBy('id')
                    ->get();

                if ($orderProducts->isEmpty()) {
                    return null;
                }

                $rows = '';
                foreach ($orderProducts as $orderProduct) {
                    $productId = (int) $orderProduct->product_id;
                    $quantity = (int) ($orderProduct->quantity ?? 1);
                    $rows .= '<li style="margin:0 0 4px 0;">'
                        .'<strong>#'.$productId.'</strong>'
                        .' &times;&nbsp;'.htmlspecialchars((string) $quantity, ENT_QUOTES, 'UTF-8')
                        .'</li>';
                }

                $headingHtml = $heading !== ''
                    ? '<div style="font-family: Arial, sans-serif; font-size:14px; font-weight:bold; color:#111827; margin-bottom:8px;">'.htmlspecialchars($heading, ENT_QUOTES, 'UTF-8').'</div>'
                    : '';

                return '<tr><td style="padding: 8px 24px;">'
                    .$headingHtml
                    .'<ul style="margin:0; padding-left:20px; font-family: Arial, sans-serif; font-size:14px; line-height:1.6; color:#374151;">'
                    .$rows
                    .'</ul>'
                    .'</td></tr>';
        }

        $registry = function_exists('cms') ? cms()->emailBlocks() : [];
        if ($type && isset($registry[$type])) {
            $class = $registry[$type];

            return $class::render($data, [
                'siteName' => $variables[':siteName:'] ?? '',
                'primaryColor' => $primaryColor,
                'textColor' => $textColor,
            ]);
        }

        return null;
    }

    /**
     * Wrapt een URL in een signed click-tracking-route zodat we kunnen
     * loggen welke link in welke stap geklikt is en, indien geconfigureerd,
     * de flow voor de order kunnen cancelen.
     */
    protected function wrapTrackedUrl(string $url, string $linkType): string
    {
        $url = trim($url);
        if ($url === '' || $url === '#') {
            return $url;
        }

        // Sla preview-renderscenario's over: zonder order-id of step-id geen tracking.
        if (! $this->order->id || ! $this->flowStep->id) {
            return $url;
        }

        try {
            return URL::signedRoute(
                'dashed.frontend.order-handled.click',
                [
                    'order' => $this->order->id,
                    'step' => $this->flowStep->id,
                    'to' => $url,
                    'type' => $linkType,
                ],
            );
        } catch (\Throwable $e) {
            return $url;
        }
    }

    protected function customsettingGet(string $key, mixed $default = null): mixed
    {
        if (class_exists(\Dashed\DashedCore\Models\Customsetting::class)
            && class_exists(\Dashed\DashedCore\Classes\Sites::class)) {
            return \Dashed\DashedCore\Models\Customsetting::get(
                $key,
                \Dashed\DashedCore\Classes\Sites::getActive(),
                $default,
            );
        }

        return $default;
    }

    protected function resolveSiteName(): string
    {
        return (string) ($this->customsettingGet('site_name') ?: config('app.name', 'Site'));
    }

    protected function resolveFromEmail(): ?string
    {
        $email = $this->customsettingGet('site_from_email');

        return $email ?: null;
    }
}
