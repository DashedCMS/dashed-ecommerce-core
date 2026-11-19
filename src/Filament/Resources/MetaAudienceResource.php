<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Illuminate\Support\HtmlString;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Dashed\DashedEcommerceCore\Models\MetaAudience;
use Dashed\DashedEcommerceCore\Jobs\SyncMetaAudienceJob;
use Dashed\DashedEcommerceCore\Services\Meta\Audiences\MetaAudienceSettings;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\MetaAudienceSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Resources\MetaAudienceResource\Pages\ListMetaAudiences;

/**
 * Doelgroepen die als Custom Audience naar Meta gaan. Geen menu-item:
 * bereikbaar via Instellingen, Doelgroepen naar Meta, en even toegankelijk
 * als die instellingenpagina.
 */
class MetaAudienceResource extends Resource
{
    protected static ?string $model = MetaAudience::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'meta-doelgroepen';

    public static function getModelLabel(): string
    {
        return __('Meta-doelgroep');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Doelgroepen naar Meta');
    }

    public static function canAccess(): bool
    {
        return MetaAudienceSettingsPage::canAccess();
    }

    public static function canViewAny(): bool
    {
        return MetaAudienceSettingsPage::canAccess();
    }

    public static function canCreate(): bool
    {
        return MetaAudienceSettingsPage::canAccess();
    }

    public static function canEdit(Model $record): bool
    {
        return MetaAudienceSettingsPage::canAccess();
    }

    public static function canDelete(Model $record): bool
    {
        return MetaAudienceSettingsPage::canAccess();
    }

    /** @return array<int, \Filament\Schemas\Components\Component> */
    public static function formFields(): array
    {
        $sites = collect(Sites::getSites())->mapWithKeys(fn ($site) => [$site['id'] => ucfirst($site['name'])])->all();

        return [
            TextInput::make('name')
                ->label(__('Naam'))
                ->helperText(__('Zo heet de doelgroep ook in Ads Manager.'))
                ->required()
                ->maxLength(100),
            Select::make('site_id')
                ->label(__('Site'))
                ->options($sites)
                ->default(array_key_first($sites))
                ->required()
                ->live()
                ->hidden(count($sites) < 2)
                ->dehydratedWhenHidden(),
            Select::make('source_type')
                ->label(__('Bron'))
                ->options(MetaAudience::sourceTypeOptions())
                ->required()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('source_id', null)),
            Select::make('source_id')
                ->label(__('Lijst, segment of prijsgroep'))
                ->options(fn (Get $get) => MetaAudience::sourceOptions($get('source_type'), $get('site_id') ?: Sites::getActive()))
                ->required()
                ->searchable(),
            Toggle::make('is_active')
                ->label(__('Actief'))
                ->default(true),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description(new HtmlString(e(__('Elke nacht gaat het verschil naar Meta, op zondag de hele doelgroep. Alleen contacten met een actieve aanmelding voor de nieuwsbrief van deze site gaan mee, gehasht met SHA-256; geblokkeerde adressen en Bol-klanten nooit.'))))
            ->columns([
                TextColumn::make('name')->label(__('Naam'))->searchable(),
                TextColumn::make('source_type')->label(__('Bron'))
                    ->formatStateUsing(fn (MetaAudience $record) => $record->sourceLabel()),
                TextColumn::make('site_id')->label(__('Site'))->toggleable(isToggledHiddenByDefault: count(Sites::getSites()) < 2),
                TextColumn::make('member_count')->label(__('Leden'))->numeric(),
                TextColumn::make('sync_status')->label(__('Status'))->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        MetaAudience::STATUS_SYNCED => __('Gesynchroniseerd'),
                        MetaAudience::STATUS_FAILED => __('Mislukt'),
                        MetaAudience::STATUS_QUEUED => __('In de wachtrij'),
                        default => __('Nog niet gesynchroniseerd'),
                    })
                    ->color(fn (?string $state) => match ($state) {
                        MetaAudience::STATUS_SYNCED => 'success',
                        MetaAudience::STATUS_FAILED => 'danger',
                        default => 'gray',
                    })
                    ->tooltip(fn (MetaAudience $record) => $record->last_error),
                TextColumn::make('last_synced_at')->label(__('Laatst gesynchroniseerd'))->dateTime('d-m-Y H:i')->placeholder(__('Nooit')),
                ToggleColumn::make('is_active')->label(__('Actief')),
            ])
            ->defaultSort('name')
            ->recordActions([
                Action::make('sync')
                    ->label(__('Synchroniseren'))
                    ->icon('heroicon-o-arrow-path')
                    ->action(fn (MetaAudience $record) => static::queueSync($record, false)),
                Action::make('fullSync')
                    ->label(__('Volledig vervangen'))
                    ->icon('heroicon-o-arrows-right-left')
                    ->requiresConfirmation()
                    ->modalDescription(__('Vervangt alle leden van de doelgroep bij Meta door de huidige lijst. Handig als iemand de doelgroep in Ads Manager heeft aangepast.'))
                    ->action(fn (MetaAudience $record) => static::queueSync($record, true)),
                Action::make('logs')
                    ->label(__('Logboek'))
                    ->icon('heroicon-o-queue-list')
                    ->color('gray')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Sluiten'))
                    ->modalContent(fn (MetaAudience $record) => view('dashed-ecommerce-core::meta-audiences.logs', [
                        'logs' => $record->logs()->limit(50)->get(),
                    ])),
                Action::make('edit')
                    ->label(__('Bewerken'))
                    ->icon('heroicon-o-pencil-square')
                    ->fillForm(fn (MetaAudience $record) => $record->only(['name', 'site_id', 'source_type', 'source_id', 'is_active']))
                    ->schema(static::formFields())
                    ->action(fn (MetaAudience $record, array $data) => $record->update($data)),
                DeleteAction::make()
                    ->modalDescription(__('Haalt de doelgroep hier weg. De Custom Audience bij Meta blijft bestaan en wordt niet meer bijgewerkt; verwijder hem zo nodig in Ads Manager.')),
            ]);
    }

    public static function queueSync(MetaAudience $audience, bool $full): void
    {
        if (! MetaAudienceSettings::for($audience->site_id)->ready()) {
            Notification::make()
                ->title(__('Doelgroepen naar Meta staan uit of het advertentieaccount of token ontbreekt'))
                ->danger()
                ->send();

            return;
        }

        $audience->forceFill(['sync_status' => MetaAudience::STATUS_QUEUED])->save();
        SyncMetaAudienceJob::dispatch($audience->id, $full);

        Notification::make()
            ->title(__('De synchronisatie staat in de wachtrij'))
            ->success()
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMetaAudiences::route('/'),
        ];
    }
}
