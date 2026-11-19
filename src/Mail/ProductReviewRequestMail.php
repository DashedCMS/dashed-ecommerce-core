<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\URL;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Mail\MailBranding;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Services\ProductReviews\ReviewRequestSender;

/**
 * "Wat vind je van je aankoop?": per gekocht product een ondertekende link
 * naar het reviewformulier. In de gedeelde maillayout van de site, met de
 * afmeldlink van de opvolgmails; wie zich daar afmeldt krijgt ook dit
 * verzoek niet meer. Teksten zijn aan te passen via Vertalingen, groep
 * product-reviews.
 */
class ProductReviewRequestMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<int, OrderProduct>  $lines
     */
    public function __construct(public Order $order, public array $lines)
    {
    }

    public function build(): static
    {
        $siteId = (string) $this->order->site_id;
        $branding = MailBranding::for($siteId);
        $primary = e($branding['primaryColor']);
        $buttonText = e($branding['textColor']);

        $vars = ['name' => (string) $this->order->first_name, 'site' => $branding['siteName'], 'invoice' => (string) $this->order->invoice_id];
        $subject = Translation::get('review-request-subject', 'product-reviews', 'Wat vind je van je aankoop bij :site:?', 'text', $vars);
        $intro = Translation::get('review-request-intro', 'product-reviews', 'Hoi :name:, je bestelling :invoice: is bezorgd. We horen graag wat je ervan vindt. Een review helpt andere klanten bij hun keuze en kost je een minuut.', 'text', $vars);
        $button = Translation::get('review-request-button', 'product-reviews', 'Schrijf een review', 'text');

        $rows = '';
        foreach ($this->lines as $line) {
            $url = e(ReviewRequestSender::reviewUrl($line));
            $image = $this->imageFor($line);
            $imageHtml = $image ? '<img src="' . e($image) . '" alt="" width="64" style="width:64px;height:auto;border-radius:6px;display:block;">' : '';

            $rows .= '<tr>'
                . '<td style="padding:12px 12px 12px 0;width:64px;vertical-align:middle;">' . $imageHtml . '</td>'
                . '<td style="padding:12px 0;vertical-align:middle;font-family:Arial,sans-serif;font-size:15px;color:#111827;">' . e((string) $line->name) . '</td>'
                . '<td style="padding:12px 0;text-align:right;vertical-align:middle;">'
                . '<a href="' . $url . '" style="display:inline-block;padding:10px 16px;background:' . $primary . ';color:' . $buttonText . ';text-decoration:none;border-radius:6px;font-family:Arial,sans-serif;font-size:14px;font-weight:bold;">' . e($button) . '</a>'
                . '</td></tr>';
        }

        $blocks = [
            '<p style="font-family:Arial,sans-serif;font-size:15px;line-height:1.5;color:#111827;margin:0 0 16px;">' . e($intro) . '</p>',
            '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">' . $rows . '</table>',
        ];

        $unsubscribeUrl = rescue(fn () => URL::signedRoute('dashed.frontend.order-handled.unsubscribe', ['order' => $this->order->getKey()]), null, false);

        $mail = $this->subject($subject)
            ->view('dashed-core::emails.layout')
            ->with(array_merge($branding, [
                'blocks' => $blocks,
                'unsubscribeUrl' => $unsubscribeUrl,
                'unsubscribeLabel' => Translation::get('review-request-unsubscribe', 'product-reviews', 'Geen mails meer over deze bestelling', 'text'),
            ]));

        $from = Customsetting::get('site_from_email', $siteId);
        if ($from) {
            $mail->from($from, $branding['siteName']);
        }

        return $mail;
    }

    protected function imageFor(OrderProduct $line): ?string
    {
        return rescue(function () use ($line) {
            $id = $line->product?->firstImage;

            return $id ? (mediaHelper()->getSingleMedia($id, 'small')->url ?? null) : null;
        }, null, false);
    }
}
