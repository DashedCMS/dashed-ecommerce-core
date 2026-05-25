<?php

namespace Dashed\DashedEcommerceCore\Filament\Actions;

use Illuminate\Support\Carbon;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Collection;

/**
 * Bulk-actie voor levertijd / verwachte voorraad. Werkt op de twee
 * velden die de POS- en webshop-flow gebruiken om de leverdatum op de
 * order_product te snapshotten (zie Product::resolvePreOrderRestockedDate):
 *
 * - `expected_in_stock_date`     absolute datum waarop voorraad terug is
 * - `expected_delivery_in_days`  relatief aantal dagen vanaf besteldatum
 *
 * Per veld kan de admin kiezen om niet te wijzigen, een vaste waarde te
 * zetten, met X te verschuiven of het veld te wissen.
 */
class BulkDeliveryTimeUpdateBulkAction
{
    public const MODE_SKIP = 'skip';
    public const MODE_REPLACE = 'replace';
    public const MODE_SHIFT = 'shift';
    public const MODE_CLEAR = 'clear';

    public static function make(): BulkAction
    {
        return BulkAction::make('changeDeliveryTime')
            ->color('primary')
            ->icon('heroicon-o-truck')
            ->label('Verander levertijd')
            ->modalHeading('Levertijd aanpassen voor geselecteerde producten')
            ->modalDescription('Voor zowel de verwachte voorraad-datum als de levertijd in dagen kun je kiezen om de waarde te vervangen, te verschuiven of te wissen.')
            ->modalSubmitActionLabel('Doorvoeren')
            ->schema(fn () => static::schema())
            ->action(function (Collection $records, array $data): void {
                $touched = static::applyToRecords($records, $data);

                Notification::make()
                    ->title($touched === 0
                        ? 'Geen wijzigingen toegepast'
                        : sprintf('%d product(en) bijgewerkt', $touched))
                    ->success($touched > 0)
                    ->warning($touched === 0)
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * @return array<int, mixed>
     */
    protected static function schema(): array
    {
        return [
            Section::make('Verwachte voorraad-datum')
                ->description('De absolute datum waarop het product weer op voorraad is (expected_in_stock_date).')
                ->schema([
                    Select::make('mode_expected_in_stock_date')
                        ->label('Aanpassing')
                        ->options([
                            self::MODE_SKIP => 'Niet wijzigen',
                            self::MODE_REPLACE => 'Vervangen door vaste datum',
                            self::MODE_SHIFT => 'Verschuiven met +/- dagen',
                            self::MODE_CLEAR => 'Wissen',
                        ])
                        ->default(self::MODE_SKIP)
                        ->required()
                        ->reactive(),
                    DatePicker::make('value_expected_in_stock_date_date')
                        ->label('Nieuwe datum')
                        ->required(fn (callable $get) => $get('mode_expected_in_stock_date') === self::MODE_REPLACE)
                        ->visible(fn (callable $get) => $get('mode_expected_in_stock_date') === self::MODE_REPLACE),
                    TextInput::make('value_expected_in_stock_date_shift')
                        ->label('Verschuiven met dagen (negatief = eerder)')
                        ->helperText('Voorbeeld: 7 verschuift een week later, -3 drie dagen eerder.')
                        ->numeric()
                        ->step(1)
                        ->required(fn (callable $get) => $get('mode_expected_in_stock_date') === self::MODE_SHIFT)
                        ->visible(fn (callable $get) => $get('mode_expected_in_stock_date') === self::MODE_SHIFT),
                ])
                ->columns(2)
                ->compact(),

            Section::make('Levertijd in dagen')
                ->description('Relatief aantal dagen vanaf besteldatum (expected_delivery_in_days). Wordt gebruikt als de absolute datum leeg is.')
                ->schema([
                    Select::make('mode_expected_delivery_in_days')
                        ->label('Aanpassing')
                        ->options([
                            self::MODE_SKIP => 'Niet wijzigen',
                            self::MODE_REPLACE => 'Vervangen door vast aantal dagen',
                            self::MODE_SHIFT => 'Aantal dagen erbij/eraf',
                            self::MODE_CLEAR => 'Wissen',
                        ])
                        ->default(self::MODE_SKIP)
                        ->required()
                        ->reactive(),
                    TextInput::make('value_expected_delivery_in_days')
                        ->label(fn (callable $get) => $get('mode_expected_delivery_in_days') === self::MODE_SHIFT
                            ? 'Aantal dagen (negatief = minder)'
                            : 'Aantal dagen')
                        ->numeric()
                        ->step(1)
                        ->minValue(fn (callable $get) => $get('mode_expected_delivery_in_days') === self::MODE_REPLACE ? 0 : null)
                        ->required(fn (callable $get) => in_array($get('mode_expected_delivery_in_days'), [self::MODE_REPLACE, self::MODE_SHIFT], true))
                        ->visible(fn (callable $get) => in_array($get('mode_expected_delivery_in_days'), [self::MODE_REPLACE, self::MODE_SHIFT], true)),
                ])
                ->columns(2)
                ->compact(),
        ];
    }

    protected static function applyToRecords(Collection $records, array $data): int
    {
        $touched = 0;

        foreach ($records as $record) {
            $changed = false;

            // expected_in_stock_date
            $dateMode = (string) ($data['mode_expected_in_stock_date'] ?? self::MODE_SKIP);
            if ($dateMode !== self::MODE_SKIP) {
                $current = $record->getRawOriginal('expected_in_stock_date');
                $new = static::computeNewDate($dateMode, $current, $data);

                if ($new !== '__unchanged__') {
                    $record->expected_in_stock_date = $new;
                    $changed = true;
                }
            }

            // expected_delivery_in_days
            $daysMode = (string) ($data['mode_expected_delivery_in_days'] ?? self::MODE_SKIP);
            if ($daysMode !== self::MODE_SKIP) {
                $current = $record->getRawOriginal('expected_delivery_in_days');
                $raw = $data['value_expected_delivery_in_days'] ?? null;
                $new = static::computeNewDays($daysMode, $current, $raw);

                if ($new !== '__unchanged__') {
                    $record->expected_delivery_in_days = $new;
                    $changed = true;
                }
            }

            if ($changed) {
                $record->save();
                $touched++;
            }
        }

        return $touched;
    }

    /**
     * Returnt '__unchanged__' als er niets te wijzigen valt, anders de nieuwe
     * waarde (Carbon date | null).
     */
    protected static function computeNewDate(string $mode, mixed $current, array $data): mixed
    {
        if ($mode === self::MODE_CLEAR) {
            return $current === null ? '__unchanged__' : null;
        }

        if ($mode === self::MODE_REPLACE) {
            $raw = $data['value_expected_in_stock_date_date'] ?? null;
            if ($raw === null || $raw === '') {
                return '__unchanged__';
            }
            $new = Carbon::parse($raw)->startOfDay();

            return static::sameDate($new, $current) ? '__unchanged__' : $new;
        }

        if ($mode === self::MODE_SHIFT) {
            $shift = (int) ($data['value_expected_in_stock_date_shift'] ?? 0);
            if ($shift === 0 || $current === null) {
                return '__unchanged__';
            }
            $new = Carbon::parse($current)->startOfDay()->addDays($shift);

            return static::sameDate($new, $current) ? '__unchanged__' : $new;
        }

        return '__unchanged__';
    }

    /**
     * Returnt '__unchanged__' als er niets te wijzigen valt, anders de nieuwe
     * waarde (int | null).
     */
    protected static function computeNewDays(string $mode, mixed $current, mixed $raw): mixed
    {
        if ($mode === self::MODE_CLEAR) {
            return $current === null ? '__unchanged__' : null;
        }

        $currentInt = ($current === null || $current === '') ? null : (int) $current;

        if ($mode === self::MODE_REPLACE) {
            if ($raw === null || $raw === '') {
                return '__unchanged__';
            }
            $new = max(0, (int) $raw);

            return $new === $currentInt ? '__unchanged__' : $new;
        }

        if ($mode === self::MODE_SHIFT) {
            if ($raw === null || $raw === '') {
                return '__unchanged__';
            }
            $shift = (int) $raw;
            if ($shift === 0) {
                return '__unchanged__';
            }
            $new = max(0, ($currentInt ?? 0) + $shift);

            return $new === $currentInt ? '__unchanged__' : $new;
        }

        return '__unchanged__';
    }

    protected static function sameDate(Carbon $new, mixed $current): bool
    {
        if ($current === null) {
            return false;
        }

        try {
            return Carbon::parse($current)->startOfDay()->equalTo($new->copy()->startOfDay());
        } catch (\Throwable) {
            return false;
        }
    }
}
