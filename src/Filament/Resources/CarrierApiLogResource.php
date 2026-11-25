<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use UnitEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Columns\TextColumn;
use Filament\Schemas\Components\Section;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Dashed\DashedEcommerceCore\Models\CarrierApiLog;
use Dashed\DashedEcommerceCore\Filament\Resources\CarrierApiLogResource\Pages\ViewCarrierApiLog;
use Dashed\DashedEcommerceCore\Filament\Resources\CarrierApiLogResource\Pages\ListCarrierApiLogs;

/**
 * Carrier-logboek: every request to Veloyd, MyParcel and other carriers with
 * what was sent and what came back. Superadmins only (request bodies hold
 * customer addresses), kept for CarrierApiLog::RETENTION_DAYS days.
 */
class CarrierApiLogResource extends Resource
{
    protected static ?string $model = CarrierApiLog::class;

    protected static string|UnitEnum|null $navigationGroup = 'E-commerce';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'carrier-logboek';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    public static function getNavigationLabel(): string
    {
        return __('Carrier-logboek');
    }

    public static function getModelLabel(): string
    {
        return __('Carrier-request');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Carrier-logboek');
    }

    /** Same rule as the role management in dashed-core: only a superadmin. */
    public static function isAllowed(): bool
    {
        return auth()->user()?->role === 'superadmin';
    }

    public static function canAccess(): bool
    {
        return static::isAllowed();
    }

    public static function canViewAny(): bool
    {
        return static::isAllowed();
    }

    public static function canView($record): bool
    {
        return static::isAllowed();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('order:id,invoice_id');
    }

    public static function statusColor(?int $status): string
    {
        return $status === null || $status >= 400 ? 'danger' : 'success';
    }

    public static function statusLabel(?int $status): string
    {
        return $status === null ? __('Geen antwoord') : (string) $status;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label(__('Datum'))->dateTime('d-m-Y H:i:s')->sortable(),
                TextColumn::make('carrier')->label(__('Carrier'))->badge()->color('gray'),
                TextColumn::make('action')->label(__('Actie'))->searchable(),
                TextColumn::make('order_id')->label(__('Order'))
                    ->formatStateUsing(fn (CarrierApiLog $record) => $record->order?->invoice_id ?: '#' . $record->order_id)
                    ->url(fn (CarrierApiLog $record) => $record->order_id
                        ? OrderResource::getUrl('view', ['record' => $record->order_id])
                        : null)
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('response_status')->label(__('Status'))->badge()
                    ->getStateUsing(fn (CarrierApiLog $record) => static::statusLabel($record->response_status))
                    ->color(fn (CarrierApiLog $record) => static::statusColor($record->response_status)),
                TextColumn::make('duration_ms')->label(__('Duur'))
                    ->formatStateUsing(fn ($state) => $state === null ? '-' : number_format((int) $state, 0, ',', '.') . ' ms')
                    ->sortable(),
                TextColumn::make('url')->label(__('URL'))->limit(60)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('failed')
                    ->label(__('Alleen mislukt'))
                    ->toggle()
                    ->query(fn (Builder $query) => $query->failed()),
                SelectFilter::make('carrier')->label(__('Carrier'))
                    ->options(fn () => CarrierApiLog::query()->distinct()->orderBy('carrier')->pluck('carrier', 'carrier')->all()),
                SelectFilter::make('action')->label(__('Actie'))
                    ->options(fn () => CarrierApiLog::query()->distinct()->orderBy('action')->pluck('action', 'action')->all()),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->recordUrl(fn (CarrierApiLog $record) => static::getUrl('view', ['record' => $record]));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Request'))
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('created_at')->label(__('Datum'))->dateTime('d-m-Y H:i:s'),
                    TextEntry::make('carrier')->label(__('Carrier')),
                    TextEntry::make('action')->label(__('Actie')),
                    TextEntry::make('order_id')->label(__('Order'))
                        ->formatStateUsing(fn (CarrierApiLog $record) => $record->order?->invoice_id ?: '#' . $record->order_id)
                        ->url(fn (CarrierApiLog $record) => $record->order_id ? OrderResource::getUrl('view', ['record' => $record->order_id]) : null)
                        ->placeholder('-'),
                    TextEntry::make('response_status')->label(__('Status'))->badge()
                        ->getStateUsing(fn (CarrierApiLog $record) => static::statusLabel($record->response_status))
                        ->color(fn (CarrierApiLog $record) => static::statusColor($record->response_status)),
                    TextEntry::make('duration_ms')->label(__('Duur'))
                        ->formatStateUsing(fn ($state) => $state === null ? '-' : $state . ' ms')
                        ->placeholder('-'),
                    TextEntry::make('method')->label(__('Methode')),
                    TextEntry::make('url')->label(__('URL'))->copyable()->columnSpanFull(),
                ]),
            Section::make(__('Verstuurd (request body)'))
                ->columnSpanFull()
                ->schema([
                    ViewEntry::make('request_body')
                        ->hiddenLabel()
                        ->state(fn (CarrierApiLog $record) => CarrierApiLog::prettyBody($record->request_body))
                        ->view('dashed-ecommerce-core::carrier-logs.body'),
                ]),
            Section::make(__('Ontvangen (response body)'))
                ->columnSpanFull()
                ->schema([
                    ViewEntry::make('response_body')
                        ->hiddenLabel()
                        ->state(fn (CarrierApiLog $record) => CarrierApiLog::prettyBody($record->response_body))
                        ->view('dashed-ecommerce-core::carrier-logs.body'),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCarrierApiLogs::route('/'),
            'view' => ViewCarrierApiLog::route('/{record}'),
        ];
    }
}
