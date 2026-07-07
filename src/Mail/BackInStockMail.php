<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedCore\Mail\Concerns\HasEmailTemplate;
use Dashed\DashedCore\Mail\Contracts\RegistersEmailTemplate;

class BackInStockMail extends Mailable implements RegistersEmailTemplate, ShouldQueue
{
    use HasEmailTemplate;
    use Queueable;
    use SerializesModels;

    public function __construct(public Product $product, public string $recipient)
    {
    }

    public static function emailTemplateName(): string
    {
        return 'Product weer op voorraad';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden naar een klant die zich heeft aangemeld voor een melding wanneer een uitverkocht product weer besteld kan worden.';
    }

    public static function availableVariables(): array
    {
        return ['productName', 'productUrl', 'siteName'];
    }

    public static function defaultSubject(): string
    {
        return ':productName: is weer op voorraad';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Goed nieuws!', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p><strong>:productName:</strong> is weer op voorraad en kan weer besteld worden.</p><p><a href=":productUrl:">Bekijk het product</a></p>']],
        ];
    }

    public static function sampleData(): array
    {
        $product = Product::query()->latest()->first();

        return [
            'product' => $product,
            'productName' => $product?->name ?? 'Voorbeeldproduct',
            'productUrl' => rescue(fn () => $product?->getUrl(), '#', false),
            'siteName' => Customsetting::get('site_name'),
        ];
    }

    public static function makeForTest(): ?self
    {
        $product = Product::query()->latest()->first();

        return $product ? new self($product, 'test@example.com') : null;
    }

    public function build()
    {
        $name = is_array($this->product->name) ? reset($this->product->name) : (string) $this->product->name;
        $url = rescue(fn () => $this->product->getUrl(), '#', false);

        $context = [
            'product' => $this->product,
            'productName' => $name,
            'productUrl' => $url,
            'siteName' => Customsetting::get('site_name'),
        ];

        $templateHtml = $this->renderFromTemplate($context);

        if ($templateHtml !== null) {
            $subject = $this->templateSubject(
                Translation::get('back-in-stock-email-subject', 'products', ':productName: is weer op voorraad', 'text', [
                    'productName' => $name,
                ]),
                $context
            );
            [$fromEmail, $fromName] = $this->templateFrom(Customsetting::get('site_from_email'), Customsetting::get('site_name'));

            return $this->to($this->recipient)
                ->html($templateHtml)
                ->from($fromEmail, $fromName)
                ->subject($subject);
        }

        return $this->to($this->recipient)
            ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
            ->subject(Translation::get('back-in-stock-email-subject', 'products', ':productName: is weer op voorraad', 'text', [
                'productName' => $name,
            ]))
            ->html('<p>Goed nieuws! <strong>' . e($name) . '</strong> is weer op voorraad.</p>'
                . '<p><a href="' . e((string) $url) . '">Bekijk het product</a></p>');
    }
}
