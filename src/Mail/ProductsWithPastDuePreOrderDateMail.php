<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedCore\Mail\Concerns\HasEmailTemplate;
use Dashed\DashedCore\Mail\Contracts\RegistersEmailTemplate;

class ProductsWithPastDuePreOrderDateMail extends Mailable implements RegistersEmailTemplate
{
    use HasEmailTemplate;
    use Queueable;
    use SerializesModels;

    public $products;

    public function __construct($products)
    {
        $this->products = $products;
    }

    public static function emailTemplateName(): string
    {
        return 'Producten met verlopen pre-order datum (beheerder)';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden naar de beheerder als er producten zijn waarvan de pre-order datum is verstreken.';
    }

    public static function availableVariables(): array
    {
        return ['productCount', 'siteName', 'primaryColor'];
    }

    public static function defaultSubject(): string
    {
        return 'Er zijn producten die aandacht nodig hebben';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Producten met verlopen pre-order datum', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>Er zijn :productCount: producten waarvan de pre-order datum is verstreken en die aandacht nodig hebben.</p><p>Log in op de beheeromgeving om deze producten te bekijken en bij te werken.</p>']],
        ];
    }

    public static function sampleData(): array
    {
        return [
            'products' => collect(),
            'productCount' => 3,
            'siteName' => Customsetting::get('site_name'),
        ];
    }

    public static function makeForTest(): ?self
    {
        return new self(collect());
    }

    public function build()
    {
        $context = [
            'products' => $this->products,
            'productCount' => is_countable($this->products) ? count($this->products) : 0,
            'siteName' => Customsetting::get('site_name'),
        ];

        $templateHtml = $this->renderFromTemplate($context);

        if ($templateHtml !== null) {
            $subject = $this->templateSubject(
                Translation::get('products-with-past-due-pre-order-date-email-subject', 'products-with-past-due-pre-order-date', 'Er zijn producten die aandacht nodig hebben'),
                $context
            );
            [$fromEmail, $fromName] = $this->templateFrom(Customsetting::get('site_from_email'), Customsetting::get('site_name'));

            return $this->html($templateHtml)->from($fromEmail, $fromName)->subject($subject);
        }

        $view = view()->exists(config('dashed-core.site_theme', 'dashed') . '.emails.products-with-past-due-pre-order-date')
            ? config('dashed-core.site_theme', 'dashed') . '.emails.products-with-past-due-pre-order-date'
            : 'dashed-ecommerce-core::emails.products-with-past-due-pre-order-date';

        return $this->view($view)
            ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
            ->subject(Translation::get('products-with-past-due-pre-order-date-email-subject', 'products-with-past-due-pre-order-date', 'Er zijn producten die aandacht nodig hebben'))
            ->with([
                'products' => $this->products,
                'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
            ]);
    }
}
