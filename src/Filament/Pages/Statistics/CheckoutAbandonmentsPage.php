<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Statistics;

use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\CheckoutAbandonment;

class CheckoutAbandonmentsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static string|UnitEnum|null $navigationGroup = 'Statistics';

    protected static ?string $title = 'Checkout-uitval';

    protected static ?string $slug = 'checkout-uitval';

    protected static ?int $navigationSort = 100050;

    protected string $view = 'dashed-ecommerce-core::statistics.pages.checkout-abandonments';

    public int $days = 30;

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('view_statistics');
    }

    /**
     * Leesbare NL-labels per reden-key.
     *
     * @return array<string, string>
     */
    public static function reasonLabels(): array
    {
        return [
            'no_items' => 'Geen producten in winkelwagen',
            'validation_failed' => 'Formulier-validatie mislukt',
            'invalid_vat_id' => 'Ongeldig btw-nummer',
            'address_rejected' => 'Adres geweigerd door vervoerder',
            'no_payment_method' => 'Geen geldige betaalmethode',
            'no_shipping_method' => 'Geen geldige verzendmethode',
            'deposit_method_missing' => 'Geen geldige aanbetaling-betaalmethode',
            'email_duplicate' => 'E-mailadres al in gebruik voor account',
            'payment_start_failed' => 'Starten van de betaling mislukt',
        ];
    }

    /**
     * Aantal uitval-events per reden binnen de gekozen periode, gesorteerd
     * van hoog naar laag.
     *
     * @return array<int, array{reason:string, label:string, count:int, share:float}>
     */
    public function rows(): array
    {
        $counts = CheckoutAbandonment::query()
            ->where('site_id', Sites::getActive())
            ->where('created_at', '>=', now()->subDays(max(1, $this->days)))
            ->selectRaw('reason, COUNT(*) as aggregate')
            ->groupBy('reason')
            ->pluck('aggregate', 'reason')
            ->toArray();

        $total = array_sum($counts);
        $labels = self::reasonLabels();

        $rows = [];
        foreach ($counts as $reason => $count) {
            $rows[] = [
                'reason' => $reason,
                'label' => $labels[$reason] ?? $reason,
                'count' => (int) $count,
                'share' => $total > 0 ? round(($count / $total) * 100, 1) : 0.0,
            ];
        }

        usort($rows, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $rows;
    }

    public function total(): int
    {
        return array_sum(array_column($this->rows(), 'count'));
    }
}
