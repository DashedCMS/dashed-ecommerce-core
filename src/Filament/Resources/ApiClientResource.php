<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Illuminate\Support\HtmlString;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ToggleColumn;
use Dashed\DashedEcommerceCore\Models\ApiClient;
use Dashed\DashedCore\Traits\HasSettingsPermission;
use Dashed\DashedEcommerceCore\Filament\Resources\ApiClientResource\Pages\ListApiClients;

/**
 * API-sleutels voor externe partijen (export-API). Geen menu-item:
 * bereikbaar via Instellingen → API-koppelingen.
 */
class ApiClientResource extends Resource
{
    use HasSettingsPermission;

    protected static ?string $model = ApiClient::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'api-koppelingen';

    public static function getModelLabel(): string
    {
        return __('API-koppeling');
    }

    public static function getPluralModelLabel(): string
    {
        return __('API-koppelingen');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description(new HtmlString(e(__('Orders ophalen kan met de sleutel als Bearer-token op')) . ' <code>' . e(url('api/v1/export/orders')) . '</code> ' . e(__('en')) . ' <code>' . e(url('api/v1/export/orders/daily')) . '</code>, ' . e(__('met optioneel start_date en end_date (JJJJ-MM-DD). Zonder datums krijg je vandaag. Er gaan geen persoonsgegevens mee.'))))
            ->columns([
                TextColumn::make('name')->label(__('Naam'))->searchable(),
                TextColumn::make('token_prefix')->label(__('Sleutel'))
                    ->formatStateUsing(fn (?string $state) => $state ? $state . '…' : '-'),
                TextColumn::make('abilities')->label(__('Toegang'))->badge()
                    ->formatStateUsing(fn (string $state) => ApiClient::abilityOptions()[$state] ?? $state),
                ToggleColumn::make('is_active')->label(__('Actief')),
                TextColumn::make('last_used_at')->label(__('Laatst gebruikt'))->dateTime('d-m-Y H:i')->placeholder(__('Nooit')),
                TextColumn::make('created_at')->label(__('Aangemaakt'))->dateTime('d-m-Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('regenerate')
                    ->label(__('Nieuwe sleutel'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription(__('De huidige sleutel werkt meteen niet meer. De partij moet de nieuwe sleutel in haar koppeling zetten.'))
                    ->action(fn (ApiClient $record) => static::showTokenOnce(__('Nieuwe sleutel aangemaakt'), $record->regenerateToken())),
                DeleteAction::make()
                    ->modalDescription(__('De sleutel werkt daarna niet meer.')),
            ]);
    }

    public static function showTokenOnce(string $title, string $token): void
    {
        Notification::make()
            ->title($title)
            ->body(new HtmlString(
                e(__('Kopieer hem nu: hij wordt niet opnieuw getoond.'))
                . '<br><code style="word-break: break-all; user-select: all;">' . e($token) . '</code>'
            ))
            ->success()
            ->persistent()
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApiClients::route('/'),
        ];
    }
}
