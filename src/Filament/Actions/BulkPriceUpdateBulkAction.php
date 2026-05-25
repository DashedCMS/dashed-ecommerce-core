<?php

namespace Dashed\DashedEcommerceCore\Filament\Actions;

use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Collection;

/**
 * Centrale "Verander prijzen" BulkAction die op zowel de ProductResource
 * lijst als de relation-managers (ProductGroup -> Products,
 * Product -> Children) gebruikt wordt. Per geregistreerd price-field
 * (`ecommerce()->builder('productPriceFields')`) krijgt de admin een
 * keuze hoe de aanpassing moet gebeuren: niet wijzigen, vervangen door
 * een vaste waarde, vast euro-bedrag erbij/eraf, of percentage erbij/eraf.
 */
class BulkPriceUpdateBulkAction
{
    public const MODE_SKIP = 'skip';
    public const MODE_REPLACE = 'replace';
    public const MODE_PLUS_EURO = 'plus_euro';
    public const MODE_PLUS_PERCENT = 'plus_percent';

    public static function make(): BulkAction
    {
        return BulkAction::make('changePrice')
            ->color('primary')
            ->label('Verander prijzen')
            ->modalHeading('Prijzen aanpassen voor geselecteerde producten')
            ->modalDescription('Per soort prijs kun je kiezen of je een vaste waarde wilt zetten, een vast euro-bedrag wilt optellen/aftrekken, of een percentage wilt toepassen.')
            ->modalSubmitActionLabel('Doorvoeren')
            ->schema(fn () => static::schemaForPriceFields())
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
    protected static function schemaForPriceFields(): array
    {
        $sections = [];

        foreach (ecommerce()->builder('productPriceFields') as $key => $priceField) {
            $modeKey = "mode_{$key}";
            $valueKey = "value_{$key}";

            $sections[] = Section::make($priceField['label'])
                ->description($priceField['helperText'] ?? null)
                ->schema([
                    Select::make($modeKey)
                        ->label('Aanpassing')
                        ->options([
                            self::MODE_SKIP => 'Niet wijzigen',
                            self::MODE_REPLACE => 'Vervangen door vaste waarde',
                            self::MODE_PLUS_EURO => 'Bedrag erbij/eraf (€)',
                            self::MODE_PLUS_PERCENT => 'Percentage erbij/eraf (%)',
                        ])
                        ->default(self::MODE_SKIP)
                        ->required()
                        ->reactive(),
                    TextInput::make($valueKey)
                        ->label(fn (callable $get) => static::valueLabelFor((string) $get($modeKey)))
                        ->helperText(fn (callable $get) => static::valueHelperTextFor((string) $get($modeKey)))
                        ->numeric()
                        ->step(0.01)
                        ->prefix(fn (callable $get) => $get($modeKey) === self::MODE_PLUS_PERCENT ? null : '€')
                        ->suffix(fn (callable $get) => $get($modeKey) === self::MODE_PLUS_PERCENT ? '%' : null)
                        ->required(fn (callable $get) => $get($modeKey) !== self::MODE_SKIP)
                        ->visible(fn (callable $get) => $get($modeKey) !== self::MODE_SKIP),
                ])
                ->columns(2)
                ->compact();
        }

        return $sections;
    }

    protected static function valueLabelFor(string $mode): string
    {
        return match ($mode) {
            self::MODE_REPLACE => 'Nieuwe waarde',
            self::MODE_PLUS_EURO => 'Bedrag in euro (negatief = eraf)',
            self::MODE_PLUS_PERCENT => 'Percentage (negatief = eraf)',
            default => 'Waarde',
        };
    }

    protected static function valueHelperTextFor(string $mode): ?string
    {
        return match ($mode) {
            self::MODE_REPLACE => 'Voorbeeld: 19.95',
            self::MODE_PLUS_EURO => 'Voorbeeld: 1.50 telt €1,50 op, -0.50 trekt €0,50 af.',
            self::MODE_PLUS_PERCENT => 'Voorbeeld: 10 verhoogt met 10%, -5 verlaagt met 5%.',
            default => null,
        };
    }

    /**
     * Past de gekozen aanpassingen toe op alle geselecteerde records.
     * Returnt het aantal records dat daadwerkelijk gewijzigd is.
     */
    protected static function applyToRecords(Collection $records, array $data): int
    {
        $priceFields = ecommerce()->builder('productPriceFields');
        $touched = 0;

        foreach ($records as $record) {
            $changed = false;

            foreach ($priceFields as $key => $priceField) {
                $mode = (string) ($data["mode_{$key}"] ?? self::MODE_SKIP);
                if ($mode === self::MODE_SKIP) {
                    continue;
                }

                $rawValue = $data["value_{$key}"] ?? null;
                if ($rawValue === null || $rawValue === '') {
                    continue;
                }
                $value = (float) $rawValue;

                $current = $record->getRawOriginal($key);
                $newValue = static::computeNewValue($mode, $current, $value);

                if ($newValue === null) {
                    continue;
                }

                $record->{$key} = $newValue;
                $changed = true;
            }

            if ($changed) {
                $record->save();
                $touched++;
            }
        }

        return $touched;
    }

    protected static function computeNewValue(string $mode, mixed $current, float $value): ?float
    {
        $currentFloat = ($current === null || $current === '') ? null : (float) $current;

        $new = match ($mode) {
            self::MODE_REPLACE => $value,
            self::MODE_PLUS_EURO => ($currentFloat ?? 0) + $value,
            self::MODE_PLUS_PERCENT => $currentFloat !== null && $currentFloat > 0
                ? $currentFloat * (1 + ($value / 100))
                : null,
            default => null,
        };

        if ($new === null) {
            return null;
        }

        // Negatieve prijzen breken cart/checkout/BTW; clamp op 0,01.
        if ($new < 0.01) {
            $new = 0.01;
        }

        $rounded = round($new, 2);
        if ($currentFloat !== null && $rounded === round($currentFloat, 2)) {
            return null;
        }

        return $rounded;
    }
}
