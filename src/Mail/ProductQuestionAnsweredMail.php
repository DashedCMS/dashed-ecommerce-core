<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Mail\MailBranding;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Models\ProductQuestion;

/**
 * Het antwoord op een productvraag, naar de vraagsteller. Een antwoord op
 * een eigen vraag, geen marketing: de nieuwsbrief-blokkadelijst geldt hier
 * niet.
 */
class ProductQuestionAnsweredMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public ProductQuestion $question)
    {
    }

    public function build(): static
    {
        $siteId = (string) $this->question->site_id;
        $branding = MailBranding::for($siteId);
        $product = $this->question->product;
        $productName = $product ? (is_array($product->name) ? (string) reset($product->name) : (string) $product->name) : '';
        $productUrl = $product ? rescue(fn () => (string) $product->getUrl(), null, false) : null;

        $vars = ['name' => (string) $this->question->name, 'product' => $productName, 'site' => $branding['siteName']];
        $subject = Translation::get('question-answered-subject', 'product-reviews', 'Antwoord op je vraag over :product:', 'text', $vars);
        $intro = Translation::get('question-answered-intro', 'product-reviews', 'Hoi :name:, bedankt voor je vraag over :product:. Hier is ons antwoord.', 'text', $vars);
        $questionLabel = Translation::get('question-answered-question', 'product-reviews', 'Je vraag', 'text');
        $answerLabel = Translation::get('question-answered-answer', 'product-reviews', 'Ons antwoord', 'text');
        $button = Translation::get('question-answered-button', 'product-reviews', 'Bekijk het product', 'text');

        $p = 'font-family:Arial,sans-serif;font-size:15px;line-height:1.5;color:#111827;margin:0 0 16px;';
        $blocks = [
            '<p style="' . $p . '">' . e($intro) . '</p>',
            '<p style="' . $p . '"><strong>' . e($questionLabel) . '</strong><br>' . nl2br(e($this->question->question)) . '</p>',
            '<p style="' . $p . '"><strong>' . e($answerLabel) . '</strong><br>' . nl2br(e((string) $this->question->answer)) . '</p>',
        ];

        // getUrl() kan een pad teruggeven (groeps-URL, of de root zonder
        // overzichtspagina); maak het absoluut tegen de site-URL.
        if ($productUrl && ! str_starts_with($productUrl, 'http')) {
            $productUrl = rtrim($branding['siteUrl'], '/') . '/' . ltrim($productUrl, '/');
        }

        if ($productUrl && str_starts_with($productUrl, 'http')) {
            $blocks[] = '<p style="margin:0 0 16px;"><a href="' . e($productUrl) . '" style="display:inline-block;padding:10px 16px;background:' . e($branding['primaryColor']) . ';color:' . e($branding['textColor']) . ';text-decoration:none;border-radius:6px;font-family:Arial,sans-serif;font-size:14px;font-weight:bold;">' . e($button) . '</a></p>';
        }

        $mail = $this->subject($subject)
            ->view('dashed-core::emails.layout')
            ->with(array_merge($branding, ['blocks' => $blocks, 'unsubscribeUrl' => null, 'unsubscribeLabel' => null]));

        $from = Customsetting::get('site_from_email', $siteId);
        if ($from) {
            $mail->from($from, $branding['siteName']);
        }

        return $mail;
    }
}
