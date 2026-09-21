<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Illuminate\Support\Facades\DB;
use Filament\Resources\Resource;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Services\OnAccount\OnAccountBalance;
use Dashed\DashedEcommerceCore\Filament\Resources\OnAccountCustomerResource\Pages\EditOnAccountCustomer;
use Dashed\DashedEcommerceCore\Filament\Resources\OnAccountCustomerResource\Pages\ListOnAccountCustomers;
use Dashed\DashedEcommerceCore\Filament\Resources\OnAccountCustomerResource\RelationManagers\OpenOrdersRelationManager;

/**
 * Klanten met een betaalmethode op rekening: hun termijn, limiet,
 * handmatige blokkade en openstaande/vervallen bedrag. Alleen klanten met
 * een op-rekening-methode van de actieve site staan in de lijst.
 */
class OnAccountCustomerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'on-account-customers';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|UnitEnum|null $navigationGroup = 'Gebruikers';

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return __('Klanten op rekening');
    }

    public static function getModelLabel(): string
    {
        return __('Klant op rekening');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Klanten op rekening');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->whereIn(
            'id',
            DB::table('dashed__payment_method_users')
                ->join('dashed__payment_methods', 'dashed__payment_methods.id', '=', 'dashed__payment_method_users.payment_method_id')
                ->where('dashed__payment_methods.on_account', true)
                ->where('dashed__payment_methods.site_id', Sites::getActive())
                ->select('dashed__payment_method_users.user_id'),
        );

        return OnAccountBalance::addOpenColumns($query);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make(__('Op rekening'))->schema([
                Select::make('payment_methods')
                    ->label(__('Betaalmethodes op rekening'))
                    ->multiple()
                    ->options(fn () => self::onAccountMethodsQuery()->get()->mapWithKeys(fn ($m) => [$m->id => $m->name]))
                    ->afterStateHydrated(fn ($component, $record) => $component->state(
                        $record ? DB::table('dashed__payment_method_users')->where('user_id', $record->id)
                            ->whereIn('payment_method_id', self::onAccountMethodsQuery()->select('id'))
                            ->pluck('payment_method_id')->all() : []
                    ))
                    ->dehydrated(false),
                TextInput::make('payment_term_days')
                    ->label(__('Betaaltermijn in dagen'))
                    ->helperText(__('Leeg is de winkelstandaard.'))
                    ->numeric()->minValue(0),
                TextInput::make('credit_limit')
                    ->label(__('Kredietlimiet'))
                    ->helperText(__('Leeg is de winkelstandaard.'))
                    ->numeric()->minValue(0),
                Toggle::make('on_account_blocked')
                    ->label(__('Handmatig geblokkeerd'))
                    ->helperText(__('De klant kan niet meer op rekening bestellen tot je dit weer uitzet.'))
                    ->afterStateHydrated(fn ($component, $record) => $component->state((bool) $record?->on_account_blocked_at))
                    ->dehydrated(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company')->label(__('Bedrijf'))->searchable(),
                TextColumn::make('email')->label(__('E-mailadres'))->searchable(),
                TextColumn::make('on_account_open')->label(__('Openstaand'))->money('EUR')->sortable(),
                TextColumn::make('on_account_overdue')->label(__('Vervallen'))->money('EUR')->sortable()
                    ->color(fn ($state) => (float) $state > 0 ? 'danger' : null),
                TextColumn::make('on_account_blocked_at')->label(__('Handmatig geblokkeerd'))->date('d-m-Y'),
            ])
            ->defaultSort('on_account_overdue', 'desc')
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [OpenOrdersRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOnAccountCustomers::route('/'),
            'edit' => EditOnAccountCustomer::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * De betaalmethodes op rekening van de actieve site, gedeeld door het
     * formulierveld en `afterSave()` zodat ze altijd dezelfde set zien.
     */
    public static function onAccountMethodsQuery()
    {
        return PaymentMethod::query()
            ->where('on_account', true)
            ->where('site_id', Sites::getActive());
    }
}
