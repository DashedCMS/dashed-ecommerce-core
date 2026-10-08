<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Dashed\DashedCore\Classes\Sites;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use Filament\Tables\Filters\SelectFilter;
use Dashed\DashedEcommerceCore\Models\MetaCapiEvent;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\MetaCapiSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Resources\MetaCapiEventResource\Pages\ListMetaCapiEvents;

/**
 * Logboek van de events naar Meta's Conversions API. Geen menu-item:
 * bereikbaar via de knop "Bekijk log" op de instellingenpagina.
 */
class MetaCapiEventResource extends Resource
{
    protected static ?string $model = MetaCapiEvent::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'meta-capi-log';

    public static function getModelLabel(): string
    {
        return __('Meta CAPI-event');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Meta CAPI log');
    }

    /** Het log is precies zo toegankelijk als de instellingenpagina (zelfde instellingenpermissie). */
    public static function canAccess(): bool
    {
        return MetaCapiSettingsPage::canAccess();
    }

    public static function canViewAny(): bool
    {
        return MetaCapiSettingsPage::canAccess();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label(__('Tijdstip'))->dateTime('d-m-Y H:i')->sortable(),
                TextColumn::make('event_name')->label(__('Event')),
                TextColumn::make('order_id')->label(__('Order'))
                    ->url(fn (MetaCapiEvent $record) => $record->order_id
                        ? OrderResource::getUrl('view', ['record' => $record->order_id])
                        : null)
                    ->placeholder('-'),
                TextColumn::make('site_id')->label(__('Site'))->toggleable(isToggledHiddenByDefault: count(Sites::getSites()) < 2),
                TextColumn::make('status')->label(__('Status'))->badge()
                    ->formatStateUsing(fn (string $state) => MetaCapiEvent::statusLabel($state))
                    ->color(fn (string $state) => match ($state) {
                        MetaCapiEvent::STATUS_SENT => 'success',
                        MetaCapiEvent::STATUS_FAILED => 'danger',
                        MetaCapiEvent::STATUS_SKIPPED => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('attempts')->label(__('Pogingen')),
                TextColumn::make('response')->label(__('Response'))
                    ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string) $state)
                    ->limit(80)
                    ->wrap(),
                TextColumn::make('sent_at')->label(__('Verstuurd op'))->dateTime('d-m-Y H:i')->placeholder('-'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label(__('Status'))->options([
                    MetaCapiEvent::STATUS_PENDING => MetaCapiEvent::statusLabel(MetaCapiEvent::STATUS_PENDING),
                    MetaCapiEvent::STATUS_SENT => MetaCapiEvent::statusLabel(MetaCapiEvent::STATUS_SENT),
                    MetaCapiEvent::STATUS_FAILED => MetaCapiEvent::statusLabel(MetaCapiEvent::STATUS_FAILED),
                    MetaCapiEvent::STATUS_SKIPPED => MetaCapiEvent::statusLabel(MetaCapiEvent::STATUS_SKIPPED),
                ]),
                SelectFilter::make('site_id')->label(__('Site'))
                    ->options(collect(Sites::getSites())->pluck('name', 'id')->all()),
            ])
            ->recordActions([
                Action::make('resend')
                    ->label(__('Opnieuw versturen'))
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->visible(fn (MetaCapiEvent $record) => $record->canResend())
                    ->action(function (MetaCapiEvent $record) {
                        $queued = $record->resend();

                        Notification::make()
                            ->title($queued ? __('Het event staat opnieuw in de wachtrij') : __('Dit event kan niet opnieuw verstuurd worden'))
                            ->{$queued ? 'success' : 'warning'}()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMetaCapiEvents::route('/'),
        ];
    }
}
