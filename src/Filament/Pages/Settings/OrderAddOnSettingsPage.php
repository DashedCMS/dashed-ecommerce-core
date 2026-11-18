<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Settings;

use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Forms\Components\CheckboxList;
use Filament\Infolists\Components\TextEntry;
use Dashed\DashedEcommerceCore\Classes\Orders;
use Dashed\DashedEcommerceCore\Models\OrderAddOn;
use Dashed\DashedCore\Traits\HasSettingsPermission;
use Dashed\DashedEcommerceCore\Models\OrderAddOnOffer;
use Dashed\DashedEcommerceCore\Classes\OrderAddOns\Settings;

/**
 * Bijbestellen: na een betaalde webshoporder kan de klant nog iets toevoegen dat zonder
 * verzendkosten in dezelfde doos meegaat. Instellingen per site, zie Classes\OrderAddOns.
 */
class OrderAddOnSettingsPage extends Page
{
    use \Dashed\DashedCore\Filament\Concerns\TranslatesPageLabels;
    use HasSettingsPermission;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-archive-box-arrow-down';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Bijbestellen';
    protected static string | UnitEnum | null $navigationGroup = 'Systeem';
    protected static ?string $title = 'Bijbestellen';

    protected string $view = 'dashed-core::settings.pages.default-settings';

    public array $data = [];

    public function mount(): void
    {
        $formData = [];
        foreach (Sites::getSites() as $site) {
            $id = $site['id'];
            $formData["order_add_ons_enabled_{$id}"] = Settings::enabled($id);
            $formData["order_add_ons_open_statuses_{$id}"] = Settings::openStatuses($id);
            $formData["order_add_ons_max_hours_{$id}"] = Settings::maxHours($id);
            $formData["order_add_ons_mail_delay_minutes_{$id}"] = Settings::mailDelayMinutes($id);
            $formData["order_add_ons_mail_cooldown_days_{$id}"] = Settings::mailCooldownDays($id);
            $formData["order_add_ons_entry_points_{$id}"] = Settings::entryPoints($id);
        }
        $this->form->fill($formData);
    }

    public function form(Schema $schema): Schema
    {
        $tabs = [];
        foreach (Sites::getSites() as $site) {
            $id = $site['id'];
            $tabs[] = Tab::make($id)->label(ucfirst($site['name']))->schema([
                TextEntry::make("order_add_ons_stats_{$id}")
                    ->label(__('Laatste 30 dagen'))
                    ->state(fn () => $this->stats()),
                Toggle::make("order_add_ons_enabled_{$id}")
                    ->label(__('Bijbestellen aan'))
                    ->helperText(__('Uit: geen aanbod en geen mail, al verstuurde links tonen dat het aanbod gesloten is. Lopende bijbestellingen gaan gewoon mee.')),
                CheckboxList::make("order_add_ons_open_statuses_{$id}")
                    ->label(__('Bijbestellen kan zolang de order in deze status staat'))
                    ->options(Orders::getFulfillmentStatusses()),
                TextInput::make("order_add_ons_max_hours_{$id}")
                    ->label(__('Aanbod open tot (uren na betaling)'))
                    ->numeric()->minValue(0)->maxValue(Settings::MAX_MAX_HOURS)->required()
                    ->helperText(__('0 = geen limiet')),
                TextInput::make("order_add_ons_mail_delay_minutes_{$id}")
                    ->label(__('Herinneringsmail versturen na (minuten na betaling)'))
                    ->numeric()->minValue(Settings::MIN_MAIL_DELAY_MINUTES)->required()
                    ->helperText(__('De mail gaat alleen als het aanbod dan nog open is en er nog niets is bijbesteld.')),
                TextInput::make("order_add_ons_mail_cooldown_days_{$id}")
                    ->label(__('Hoogstens één herinneringsmail per e-mailadres per (dagen)'))
                    ->numeric()->minValue(0)->maxValue(Settings::MAX_MAIL_COOLDOWN_DAYS)->required(),
                CheckboxList::make("order_add_ons_entry_points_{$id}")
                    ->label(__('Aanbod tonen op'))
                    ->options(Settings::entryPointOptions()),
            ]);
        }

        return $schema->schema([Tabs::make('Sites')->tabs($tabs)])->statePath('data');
    }

    public function submit(): void
    {
        $state = $this->form->getState();
        foreach (Sites::getSites() as $site) {
            $id = $site['id'];
            Settings::save([
                'enabled' => (bool) ($state["order_add_ons_enabled_{$id}"] ?? false),
                'open_statuses' => $state["order_add_ons_open_statuses_{$id}"] ?? [],
                'max_hours' => $state["order_add_ons_max_hours_{$id}"] ?? null,
                'mail_delay_minutes' => $state["order_add_ons_mail_delay_minutes_{$id}"] ?? null,
                'mail_cooldown_days' => $state["order_add_ons_mail_cooldown_days_{$id}"] ?? null,
                'entry_points' => $state["order_add_ons_entry_points_{$id}"] ?? [],
            ], $id);
        }
        Notification::make()->title(__('Instellingen opgeslagen'))->success()->send();
    }

    /** Korte stand van de laatste 30 dagen, over alle sites heen. */
    public function stats(): string
    {
        $from = now()->subDays(30);
        $ordered = OrderAddOn::query()->whereNotNull('paid_at')->where('paid_at', '>=', $from);

        return sprintf(
            'Aangeboden: %d. Gemaild: %d. Geklikt: %d. Bijbesteld: %d (waarvan %d toch los verzonden).',
            OrderAddOnOffer::query()->where('shown_at', '>=', $from)->count(),
            OrderAddOnOffer::query()->where('mail_sent_at', '>=', $from)->count(),
            OrderAddOnOffer::query()->where('clicked_at', '>=', $from)->count(),
            (clone $ordered)->count(),
            (clone $ordered)->where('status', OrderAddOn::SEPARATE)->count(),
        );
    }
}
