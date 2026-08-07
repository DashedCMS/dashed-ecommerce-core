<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Settings;

use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedCore\Traits\HasSettingsPermission;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Dashed\DashedPages\Models\Page as PageModel;

class ReturnSettingsPage extends Page
{
    use HasSettingsPermission;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-arrow-uturn-left';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Retour-instellingen';
    protected static string | UnitEnum | null $navigationGroup = 'Retouren';
    protected static ?string $title = 'Retour-instellingen';
    protected static ?int $navigationSort = 10;

    protected string $view = 'dashed-core::settings.pages.default-settings';

    public array $data = [];

    public function mount(): void
    {
        $maxAmount = Customsetting::get('returns_auto_accept_max_amount');

        $this->form->fill([
            'return_page_id' => Customsetting::get('return_page_id'),
            'returns_auto_accept_enabled' => (bool) Customsetting::get('returns_auto_accept_enabled', null, false),
            'returns_auto_accept_max_days' => (int) Customsetting::get('returns_auto_accept_max_days', null, 14),
            'returns_auto_accept_excluded_category_ids' => (array) (Customsetting::get('returns_auto_accept_excluded_category_ids') ?: []),
            'returns_auto_accept_excluded_order_origins' => (array) (Customsetting::get('returns_auto_accept_excluded_order_origins') ?: []),
            'returns_auto_accept_max_amount' => ($maxAmount === '' ? null : $maxAmount),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $categoryOptions = ProductCategory::all()->pluck('name', 'id');

        $orderOrigins = [];
        foreach (Order::distinct('order_origin')->pluck('order_origin')->unique() as $orderOrigin) {
            if ($orderOrigin) {
                $orderOrigins[$orderOrigin] = ucfirst($orderOrigin);
            }
        }

        return $schema
            ->statePath('data')
            ->schema([
                Section::make(__('Retourpagina'))
                    ->schema([
                        Select::make('return_page_id')
                            ->label(__('Standaard retourpagina'))
                            ->helperText(__('De pagina met het retourformulier waar klanten hun retour aanmelden. Wordt automatisch aangemaakt; hier kun je een andere pagina kiezen.'))
                            ->searchable()
                            ->options(PageModel::pluck('name', 'id')),
                    ]),

                Section::make(__('Automatisch goedkeuren'))
                    ->schema([
                        Toggle::make('returns_auto_accept_enabled')
                            ->label(__('Retouren automatisch goedkeuren'))
                            ->helperText(__('Keur binnenkomende retouren automatisch goed wanneer ze aan de onderstaande voorwaarden voldoen.')),
                        TextInput::make('returns_auto_accept_max_days')
                            ->label(__('Automatisch goedkeuren binnen (dagen)'))
                            ->numeric()
                            ->minValue(1)
                            ->default(14)
                            ->suffix(__('dagen')),
                    ])
                    ->columns(2),

                Section::make(__('Uitsluitingen'))
                    ->schema([
                        Select::make('returns_auto_accept_excluded_category_ids')
                            ->label(__('Uitgesloten categorieen'))
                            ->helperText(__('Retouren met een product uit deze categorieen worden niet automatisch goedgekeurd.'))
                            ->multiple()
                            ->searchable()
                            ->options($categoryOptions),
                        Select::make('returns_auto_accept_excluded_order_origins')
                            ->label(__('Uitgesloten herkomsten (order origins)'))
                            ->helperText(__('Retouren van bestellingen met deze herkomst worden niet automatisch goedgekeurd.'))
                            ->multiple()
                            ->searchable()
                            ->options($orderOrigins),
                        TextInput::make('returns_auto_accept_max_amount')
                            ->label(__('Maximaal retourbedrag (leeg = geen limiet)'))
                            ->numeric()
                            ->minValue(0)
                            ->prefix(__('€')),
                    ])
                    ->columns(2),
            ]);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('Opslaan'))
                ->action('save'),
        ];
    }

    public function submit(): void
    {
        $this->save();
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Customsetting::set('return_page_id', $data['return_page_id'] ?? '');
        Customsetting::set('returns_auto_accept_enabled', ! empty($data['returns_auto_accept_enabled']) ? '1' : '0');
        Customsetting::set('returns_auto_accept_max_days', (int) ($data['returns_auto_accept_max_days'] ?: 14));
        Customsetting::set('returns_auto_accept_excluded_category_ids', array_values(array_map('intval', (array) ($data['returns_auto_accept_excluded_category_ids'] ?? []))));
        Customsetting::set('returns_auto_accept_excluded_order_origins', array_values((array) ($data['returns_auto_accept_excluded_order_origins'] ?? [])));

        $maxAmount = $data['returns_auto_accept_max_amount'] ?? null;
        Customsetting::set('returns_auto_accept_max_amount', ($maxAmount === '' || $maxAmount === null) ? '' : $maxAmount);

        Notification::make()->title(__('Instellingen opgeslagen'))->success()->send();
    }
}
