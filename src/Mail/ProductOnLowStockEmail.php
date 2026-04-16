<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedCore\Mail\Concerns\HasEmailTemplate;
use Dashed\DashedCore\Notifications\DTOs\TelegramSummary;
use Dashed\DashedCore\Mail\Contracts\RegistersEmailTemplate;
use Dashed\DashedCore\Notifications\Contracts\SendsToTelegram;

class ProductOnLowStockEmail extends Mailable implements RegistersEmailTemplate, SendsToTelegram
{
    use HasEmailTemplate;
    use Queueable;
    use SerializesModels;

    public Product $product;

    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    public static function emailTemplateName(): string
    {
        return 'Product bijna uitverkocht (beheerder)';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden naar de beheerder als een product op bijna niet meer op voorraad is.';
    }

    public static function availableVariables(): array
    {
        return ['productName', 'productSku', 'productStock', 'siteName', 'primaryColor'];
    }

    public static function defaultSubject(): string
    {
        return 'Product :productName: is bijna uitverkocht';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Product bijna uitverkocht', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>Het product <strong>:productName:</strong> (SKU: :productSku:) heeft nog maar :productStock: stuks op voorraad.</p><p>Vul de voorraad aan om te voorkomen dat het product niet meer besteld kan worden.</p>']],
        ];
    }

    public static function sampleData(): array
    {
        $product = Product::query()->latest()->first();

        return [
            'product' => $product,
            'productName' => $product?->name ?? 'Voorbeeldproduct',
            'productSku' => $product?->sku ?? 'DEMO-SKU',
            'productStock' => $product?->stock ?? 0,
            'siteName' => Customsetting::get('site_name'),
        ];
    }

    public static function makeForTest(): ?self
    {
        $product = Product::query()->latest()->first();

        return $product ? new self($product) : null;
    }

    public function build()
    {
        $context = [
            'product' => $this->product,
            'productName' => $this->product->name,
            'productSku' => $this->product->sku,
            'productStock' => $this->product->stock,
            'siteName' => Customsetting::get('site_name'),
        ];

        $templateHtml = $this->renderFromTemplate($context);

        if ($templateHtml !== null) {
            $subject = $this->templateSubject(
                Translation::get('product-low-stock-email-subject', 'products', 'Product :productName: is bijna uitverkocht', 'text', [
                    'productName' => $this->product->name,
                ]),
                $context
            );
            [$fromEmail, $fromName] = $this->templateFrom(Customsetting::get('site_from_email'), Customsetting::get('site_name'));

            return $this->html($templateHtml)->from($fromEmail, $fromName)->subject($subject);
        }

        $view = view()->exists(config('dashed-core.site_theme', 'dashed') . '.emails.product-low-stock')
            ? config('dashed-core.site_theme', 'dashed') . '.emails.product-low-stock'
            : 'dashed-ecommerce-core::emails.product-low-stock';

        return $this->view($view)
            ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
            ->subject(Translation::get('product-low-stock-email-subject', 'products', 'Product :productName: is bijna uitverkocht', 'text', [
                'productName' => $this->product->name,
            ]))
            ->with([
                'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
            ]);
    }

    public function telegramSummary(): TelegramSummary
    {
        return new TelegramSummary(
            title: 'Lage voorraad: ' . ($this->product->name ?? '-'),
            fields: [
                'Voorraad' => (string) ($this->product->stock ?? 0),
                'Drempel' => (string) ($this->product->low_stock_notification_limit ?? '-'),
                'SKU' => $this->product->sku ?? null,
            ],
            adminUrl: rescue(fn () => route('filament.admin.resources.products.edit', ['record' => $this->product->id]), null, false),
            emoji: '⚠️',
        );
    }
}
