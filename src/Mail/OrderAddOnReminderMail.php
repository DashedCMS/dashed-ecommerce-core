<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Mail\EmailRenderer;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedCore\Models\EmailTemplate;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedCore\Mail\Concerns\HasEmailTemplate;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Classes\OrderAddOns\Offer;
use Dashed\DashedCore\Mail\Contracts\RegistersEmailTemplate;

/**
 * Herinnering na een betaalde webshopbestelling: "Nog iets vergeten?" met drie voorstellen die zonder
 * verzendkosten in dezelfde doos meegaan, en de ondertekende link naar /bijbestellen/{order}.
 * Variabele `deadline` is de volledige zinsnede ("tot morgen 14:00, of tot we je bestelling inpakken").
 */
class OrderAddOnReminderMail extends Mailable implements RegistersEmailTemplate
{
    use HasEmailTemplate;
    use Queueable;
    use SerializesModels;

    /** @param  array<int, int>  $productIds  de voorstellen, vastgelegd bij het versturen */
    public function __construct(public Order $order, public array $productIds = [], public string $deadline = '')
    {
    }

    public static function emailTemplateName(): string
    {
        return 'Nog iets vergeten? Bijbestellen zonder verzendkosten (klant)';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Na betaling (wachttijd in Instellingen > Bijbestellen), één keer per order, alleen zolang de bestelling nog bijbesteld kan worden. Drie voorstellen die in dezelfde doos meegaan.';
    }

    public static function availableVariables(): array
    {
        return ['firstName', 'orderNumber', 'deadline', 'products', 'url', 'siteName', 'primaryColor'];
    }

    public static function defaultSubject(): string
    {
        return 'Nog iets vergeten? Het kan nog mee in je doos';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Nog iets vergeten?', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>Hallo :firstName:,</p><p>Bedankt voor je bestelling #:orderNumber:. We zijn hem nog niet aan het inpakken, dus er kan nog iets bij: zonder verzendkosten, in dezelfde doos.</p>']],
            ['type' => 'html', 'data' => ['html' => ':products:']],
            ['type' => 'button', 'data' => ['label' => 'Bekijk wat er nog bij kan', 'url' => ':url:', 'background' => ':primaryColor:', 'color' => '#ffffff']],
            ['type' => 'text', 'data' => ['body' => '<p>Dit kan :deadline:. Je betaalt alleen de producten.</p><p>Met vriendelijke groet,<br>:siteName:</p>']],
        ];
    }

    public static function sampleData(): array
    {
        return [
            'firstName' => 'Jan',
            'orderNumber' => '4321',
            'deadline' => 'tot dinsdag 14:30, of tot we je bestelling inpakken',
            'products' => self::productsHtml(collect(), url('/')),
            'url' => url('/'),
            'siteName' => Customsetting::get('site_name'),
        ];
    }

    public static function makeForTest(): ?self
    {
        $order = Order::query()->where('status', 'paid')->where('order_origin', 'own')->latest('id')->first();

        return $order ? new self($order, [], 'tot dinsdag 14:30, of tot we je bestelling inpakken') : null;
    }

    public function build()
    {
        $siteName = Customsetting::get('site_name', Sites::getActive(), '');
        $primaryColor = Customsetting::get('mail_primary_color', Sites::getActive(), '') ?: '#111827';
        $url = Offer::url($this->order, ['via' => 'mail']);
        $products = $this->productIds === []
            ? collect()
            : Product::with('productGroup')->whereIn('id', $this->productIds)->get()
                ->sortBy(fn (Product $product) => array_search($product->id, $this->productIds))->values();

        $context = [
            'order' => $this->order,
            'firstName' => trim((string) $this->order->first_name) ?: 'daar',
            'orderNumber' => Offer::orderNumber($this->order),
            'deadline' => $this->deadline,
            'products' => self::productsHtml($products, $url),
            'url' => $url,
            'siteName' => $siteName,
            'primaryColor' => $primaryColor,
        ];

        [$fromEmail, $fromName] = $this->templateFrom(Customsetting::get('site_from_email'), Customsetting::get('site_name'));
        $templateHtml = $this->renderFromTemplate($context);

        if ($templateHtml !== null) {
            return $this->html($templateHtml)
                ->from($fromEmail, $fromName)
                ->subject($this->templateSubject(self::defaultSubject(), $context));
        }

        $defaultTemplate = new EmailTemplate();
        $defaultTemplate->mailable_key = static::emailTemplateKey();
        $defaultTemplate->setTranslations('subject', [app()->getLocale() => self::defaultSubject()]);
        $defaultTemplate->setTranslations('blocks', [app()->getLocale() => static::defaultBlocks()]);

        return $this->html(app(EmailRenderer::class)->render($defaultTemplate, $context))
            ->from($fromEmail, $fromName)
            ->subject(self::defaultSubject());
    }

    /** @param  Collection<int, Product>  $products */
    public static function productsHtml(Collection $products, string $url): string
    {
        $rows = $products->map(function (Product $product) use ($url) {
            $image = $product->firstImage ?? $product->productGroup?->firstImage;
            $photo = $image ? rescue(fn () => mediaHelper()->getSingleMedia($image, ['fit' => [160, 160]])->url ?? null, null, false) : null;
            $name = e((string) $product->name);

            return '<tr>'
                .($photo ? '<td width="88" style="padding:8px 12px 8px 0;"><a href="'.e($url).'"><img src="'.e($photo).'" width="80" height="80" alt="'.$name.'" style="border-radius:8px;width:80px;height:80px;object-fit:cover;display:block;"></a></td>' : '<td width="1"></td>')
                .'<td style="padding:8px 0;font-size:14px;line-height:1.4;color:#111827;"><a href="'.e($url).'" style="color:#111827;text-decoration:none;"><strong>'.$name.'</strong></a></td>'
                .'<td align="right" style="padding:8px 0 8px 12px;font-size:14px;font-weight:700;color:#111827;white-space:nowrap;">'.e(CurrencyHelper::formatPrice($product->getRawOriginal('current_price'))).'</td>'
                .'</tr>';
        })->implode('');

        return $rows === '' ? '' : '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">'.$rows.'</table>';
    }
}
