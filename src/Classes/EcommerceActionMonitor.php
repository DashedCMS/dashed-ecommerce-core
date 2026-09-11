<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Illuminate\Support\Facades\Cache;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedCore\Classes\AdminActionMonitor;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;

/**
 * De e-commercekant van AdminActionMonitor: geldvelden op een product, een
 * betaalmethode die aan of uit gaat, en een gebruiker die in korte tijd te
 * veel orders handmatig op betaald zet. Model-events, dus onafhankelijk
 * van welk scherm of script de wijziging doet; alleen wat een ingelogde
 * gebruiker doet telt.
 */
class EcommerceActionMonitor
{
    /**
     * current_price en de omgerekende prijzen zijn afgeleide kolommen en
     * blijven buiten beeld.
     */
    public const PRODUCT_MONEY_FIELDS = ['price', 'new_price', 'discount_price', 'purchase_price', 'min_price', 'max_price', 'vat_rate'];

    public static function register(): void
    {
        Product::updated(fn (Product $product) => self::productUpdated($product));
        PaymentMethod::updated(fn (PaymentMethod $method) => self::paymentMethodUpdated($method));
        OrderLog::created(fn (OrderLog $log) => self::orderLogCreated($log));
    }

    protected static function productUpdated(Product $product): void
    {
        if (! auth()->check()) {
            return;
        }

        $changed = array_values(array_filter(
            self::PRODUCT_MONEY_FIELDS,
            fn (string $field) => $product->wasChanged($field) && ! self::moneyValuesAreEqual($product->getOriginal($field), $product->{$field}),
        ));

        if ($changed === []) {
            return;
        }

        $name = is_array($product->getRawOriginal('name')) ? implode(' / ', $product->getRawOriginal('name')) : (string) $product->name;
        $facts = [__('Product') => '#' . $product->id . ' ' . $name];

        foreach ($changed as $field) {
            $facts[$field] = self::formatMoneyValue($product->getOriginal($field)) . ' -> ' . self::formatMoneyValue($product->{$field});
        }

        $title = __('Geldvelden gewijzigd: :naam', ['naam' => $name]);

        if (in_array('price', $changed, true)) {
            $old = (float) $product->getOriginal('price');
            $new = (float) $product->price;

            if ($old > 0 && $new < $old * (float) config('dashed-ecommerce-core.security.alert_price_drop_fraction', 0.5)) {
                $title = __('Prijs drastisch verlaagd: :naam', ['naam' => $name]);
            }
        }

        AdminActionMonitor::alert($title, $facts);
    }

    /**
     * Eloquent vergelijkt zonder cast tekstueel, dus "75.00" uit de database
     * tegenover "75" uit het formulier telt als wijziging terwijl het bedrag
     * gelijk is. Hier telt alleen een echt andere waarde.
     */
    public static function moneyValuesAreEqual(mixed $old, mixed $new): bool
    {
        $oldEmpty = $old === null || $old === '';
        $newEmpty = $new === null || $new === '';

        if ($oldEmpty || $newEmpty) {
            return $oldEmpty && $newEmpty;
        }

        if (is_numeric($old) && is_numeric($new)) {
            return abs((float) $old - (float) $new) < 0.00001;
        }

        return (string) $old === (string) $new;
    }

    protected static function formatMoneyValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return __('leeg');
        }

        return is_numeric($value) ? number_format((float) $value, 2, ',', '.') : (string) $value;
    }

    protected static function paymentMethodUpdated(PaymentMethod $method): void
    {
        if (! auth()->check() || ! $method->wasChanged('active')) {
            return;
        }

        AdminActionMonitor::alert(
            $method->active ? __('Betaalmethode ingeschakeld: :naam', ['naam' => $method->name]) : __('Betaalmethode uitgeschakeld: :naam', ['naam' => $method->name]),
            [
                __('Betaalmethode') => '#' . $method->id . ' ' . $method->name,
                __('PSP') => (string) $method->psp,
                __('Actief') => $method->active ? __('ja') : __('nee'),
            ],
        );
    }

    protected static function orderLogCreated(OrderLog $log): void
    {
        if ($log->tag !== 'order.marked-as-paid' || ! auth()->check()) {
            return;
        }

        $userId = $log->user_id ?? auth()->id();
        $threshold = (int) config('dashed-ecommerce-core.security.alert_marked_paid_per_10_min', 10);

        if ($threshold <= 0) {
            return;
        }

        $count = OrderLog::query()
            ->where('tag', 'order.marked-as-paid')
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->count();

        if ($count < $threshold) {
            return;
        }

        // Een melding per gebruiker per uur, anders honderden mails in een aanval.
        if (! Cache::add('dashed.admin-monitor.marked-paid:' . ($userId ?? 'none'), 1, now()->addHour())) {
            return;
        }

        AdminActionMonitor::alert(__('Veel orders handmatig op betaald gezet'), [
            __('Gebruiker') => (string) ($userId ?? __('onbekend')),
            __('Aantal in tien minuten') => (string) $count,
            __('Laatste bestelling') => '#' . $log->order_id,
        ]);
    }
}
