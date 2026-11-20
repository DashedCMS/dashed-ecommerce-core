<?php

namespace Dashed\DashedEcommerceCore\CustomerProfile;

use Dashed\DashedCore\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedEcommerceCore\Models\Order;

/**
 * Eén klant zoals het CMS hem ziet: een account, of voor een gast alleen
 * een e-mailadres. Alle cijfers komen uit database-aggregaten.
 *
 * Secties van andere pakketten melden zich aan met
 * cms()->builder('customerProfileSections', ['sleutel' => Klasse::class]).
 * Zo'n klasse heeft drie statische methodes (geen interface, zodat een
 * pakket niets van ec-core hoeft te kennen):
 *
 * - label(): string
 * - sort(): int (lager staat hoger)
 * - forCustomer(?int $userId, ?string $email): ?array
 *
 * forCustomer() geeft null (sectie verbergen) of een array met
 * 'stats' => [['label' => ..., 'value' => ...]], 'columns' => [sleutel => kop],
 * 'rows' => [[sleutel => waarde, '_url' => ?string]], 'empty' => tekst en
 * optioneel 'url' (link naar het volledige overzicht). Waarden zijn platte
 * tekst; de pagina escapet ze.
 */
class CustomerProfile
{
    public const PAID_STATUSES = ['paid', 'waiting_for_confirmation', 'partially_paid'];

    public function __construct(public ?User $user, public ?string $email)
    {
    }

    public static function resolve(?int $userId, ?string $email): ?self
    {
        $user = $userId ? User::find($userId) : null;

        if ($userId && ! $user) {
            return null;
        }

        $email = $user?->email ?: (filled($email) ? mb_strtolower(trim((string) $email)) : null);

        if (! $user && ! $email) {
            return null;
        }

        // Een gast met een account: toon het account, dan klopt alles.
        if (! $user && $email) {
            $user = User::where('email', $email)->first();
        }

        return new self($user, $email);
    }

    public function userId(): ?int
    {
        return $this->user?->id;
    }

    public function name(): string
    {
        $name = trim(($this->user?->first_name ?? '') . ' ' . ($this->user?->last_name ?? ''));

        if ($name === '' && $this->email) {
            $order = Order::query()->where('email', $this->email)->latest('id')->first(['first_name', 'last_name']);
            $name = trim(($order?->first_name ?? '') . ' ' . ($order?->last_name ?? ''));
        }

        return $name !== '' ? $name : (string) $this->email;
    }

    public static function ordersFor(?int $userId, ?string $email): Builder
    {
        return Order::query()->where(function (Builder $query) use ($userId, $email) {
            if ($userId) {
                $query->orWhere('user_id', $userId);
            }

            if ($email) {
                $query->orWhere('email', $email);
            }

            if (! $userId && ! $email) {
                $query->whereRaw('1 = 0');
            }
        });
    }

    /**
     * Besteding, aantal, gemiddelde en laatste bestelling in één query.
     * Alleen betaalde bestellingen, zonder creditorders en proforma's.
     *
     * @return array{count: int, total: float, average: float, last_order_at: ?string}
     */
    public function summary(): array
    {
        $row = self::ordersFor($this->userId(), $this->email)
            ->whereIn('status', self::PAID_STATUSES)
            ->whereNull('credit_for_order_id')
            ->whereNull('replaced_by_order_id')
            ->where(fn (Builder $q) => $q->whereNull('invoice_id')->orWhereNotIn('invoice_id', ['PROFORMA', 'RETURN']))
            ->toBase()
            ->selectRaw('count(*) as aantal, coalesce(sum(total), 0) as totaal, max(created_at) as laatste')
            ->first();

        $count = (int) ($row->aantal ?? 0);
        $total = (float) ($row->totaal ?? 0);

        return [
            'count' => $count,
            'total' => $total,
            'average' => $count > 0 ? round($total / $count, 2) : 0.0,
            'last_order_at' => $row->laatste ?? null,
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, data: array}>
     */
    public function sections(): array
    {
        $sections = [];

        foreach ((array) cms()->builder('customerProfileSections') as $key => $class) {
            if (! is_string($class) || ! class_exists($class) || ! method_exists($class, 'forCustomer')) {
                continue;
            }

            $data = rescue(fn () => $class::forCustomer($this->userId(), $this->email), null);

            if (! is_array($data)) {
                continue;
            }

            $sections[] = [
                'key' => (string) $key,
                'label' => method_exists($class, 'label') ? (string) $class::label() : (string) $key,
                'sort' => method_exists($class, 'sort') ? (int) $class::sort() : 100,
                'data' => $data,
            ];
        }

        usort($sections, fn ($a, $b) => $a['sort'] <=> $b['sort']);

        return $sections;
    }
}
