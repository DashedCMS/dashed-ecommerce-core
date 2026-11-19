<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages;

use UnitEnum;
use BackedEnum;
use Livewire\Attributes\Url;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\CustomerNote;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\CustomerProfile\CustomerProfile;

/**
 * Klant 360: alles over één klant op één scherm. Te openen vanaf de
 * gebruikerslijst en vanaf een bestelling, of door een e-mailadres te
 * zoeken. Werkt ook voor gasten (alleen een e-mailadres).
 */
class CustomerProfilePage extends Page
{
    use \Dashed\DashedCore\Filament\Concerns\TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static string|UnitEnum|null $navigationGroup = 'Gebruikers';

    protected static ?string $navigationLabel = 'Klantprofiel';

    protected static ?string $title = 'Klantprofiel';

    protected static ?string $slug = 'klantprofiel';

    protected static ?int $navigationSort = 3;

    protected string $view = 'dashed-ecommerce-core::filament.pages.customer-profile';

    #[Url]
    public ?int $user = null;

    #[Url]
    public ?string $email = null;

    public string $search = '';

    public string $note = '';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('view_user') && auth()->user()->can('view_order');
    }

    public static function urlFor(?int $userId, ?string $email = null): string
    {
        return static::getUrl(array_filter(['user' => $userId, 'email' => $userId ? null : $email]));
    }

    public static function urlForOrder(Order $order): string
    {
        return static::urlFor($order->user_id, $order->email);
    }

    public function mount(): void
    {
        $this->search = (string) ($this->email ?? '');
    }

    public function profile(): ?CustomerProfile
    {
        return CustomerProfile::resolve($this->user, $this->email);
    }

    public function find(): void
    {
        $this->user = null;
        $this->email = filled($this->search) ? mb_strtolower(trim($this->search)) : null;
    }

    public function addNote(): void
    {
        abort_unless(auth()->user()->can('edit_user'), 403);

        $profile = $this->profile();
        $note = trim($this->note);

        if (! $profile || $note === '') {
            return;
        }

        CustomerNote::create([
            'user_id' => $profile->userId(),
            'email' => $profile->email,
            'author_id' => auth()->id(),
            'note' => mb_substr($note, 0, 5000),
        ]);

        $this->note = '';

        Notification::make()->title(__('Notitie opgeslagen'))->success()->send();
    }

    public function deleteNote(int $noteId): void
    {
        abort_unless(auth()->user()->can('edit_user'), 403);

        $profile = $this->profile();

        if ($profile) {
            $this->notesQuery($profile)->whereKey($noteId)->delete();
        }
    }

    public function notes(CustomerProfile $profile)
    {
        return $this->notesQuery($profile)->with('author')->latest('id')->limit(50)->get();
    }

    protected function notesQuery(CustomerProfile $profile)
    {
        return CustomerNote::query()->where(fn ($q) => $q->whereRaw('1 = 0')
            ->when($profile->userId(), fn ($q) => $q->orWhere('user_id', $profile->userId()))
            ->when($profile->email, fn ($q) => $q->orWhere('email', $profile->email)));
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public function summaryStats(CustomerProfile $profile): array
    {
        $summary = $profile->summary();

        return [
            ['label' => __('Totale besteding'), 'value' => CurrencyHelper::formatPrice($summary['total'])],
            ['label' => __('Aantal bestellingen'), 'value' => (string) $summary['count']],
            ['label' => __('Gemiddelde bestelling'), 'value' => CurrencyHelper::formatPrice($summary['average'])],
            ['label' => __('Laatste bestelling'), 'value' => $summary['last_order_at'] ? \Illuminate\Support\Carbon::parse($summary['last_order_at'])->format('d-m-Y') : '-'],
        ];
    }
}
