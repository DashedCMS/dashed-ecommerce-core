<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use BackedEnum;
use Dashed\DashedEcommerceCore\Enums\PrinterType;
use Dashed\DashedEcommerceCore\Filament\Resources\PrinterResource\Pages;
use Dashed\DashedEcommerceCore\Models\Printer;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

class PrinterResource extends Resource
{
    protected static ?string $model = Printer::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-printer';

    protected static string|UnitEnum|null $navigationGroup = 'Print queue';

    protected static ?string $navigationLabel = 'Printers';

    protected static ?string $modelLabel = 'Printer';

    protected static ?string $pluralModelLabel = 'Printers';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Printer details')
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')->required()->maxLength(100),
                    TextInput::make('location')->maxLength(100),
                    Select::make('type')
                        ->label('Doel')
                        ->options(PrinterType::options())
                        ->required(),
                    Select::make('cups_name')
                        ->label('Actieve CUPS-printer op deze Pi')
                        ->helperText('Kies uit de printers die de Pi heeft ontdekt en aan dit CMS heeft gemeld.')
                        ->options(function (?Printer $record): array {
                            if (! $record || ! $record->cups_printers) {
                                return [];
                            }

                            $options = [];
                            foreach ($record->cups_printers as $entry) {
                                $cupsName = $entry['cups_name'] ?? null;
                                if (! $cupsName) {
                                    continue;
                                }
                                $label = $cupsName;
                                if (! empty($entry['make_and_model'])) {
                                    $label .= ' (' . $entry['make_and_model'] . ')';
                                } elseif (! empty($entry['device_uri'])) {
                                    $label .= ' (' . $entry['device_uri'] . ')';
                                }
                                $options[$cupsName] = $label;
                            }

                            return $options;
                        })
                        ->visible(fn (?Printer $record): bool => $record !== null && filled($record->cups_printers))
                        ->placeholder('Kies een ontdekte printer'),
                    TextInput::make('hostname')
                        ->label('Pi hostname')
                        ->disabled()
                        ->dehydrated(false)
                        ->visible(fn (?Printer $record): bool => $record !== null),
                    TextInput::make('max_retries')->numeric()->minValue(0)->maxValue(10)->default(3),
                    Toggle::make('is_active')->default(true),
                    Placeholder::make('last_ping_status')
                        ->label('Laatste ping')
                        ->content(fn (?Printer $record): string => $record?->last_ping_at
                            ? $record->last_ping_at->diffForHumans() . ' (' . ($record->isOnline() ? 'online' : 'offline') . ')'
                            : 'Nog niet gepingd'),
                ])
                ->columns(2),
            Section::make('Pairing status')
                ->columnSpanFull()
                ->visible(fn (?Printer $record) => $record !== null)
                ->schema([
                    Placeholder::make('pairing_status')
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->content(function (?Printer $record): HtmlString {
                            if (! $record) {
                                return new HtmlString('');
                            }

                            if ($record->isPaired()) {
                                return new HtmlString(
                                    '<div style="background-color: #d1fae5; border-left: 4px solid #059669; border-radius: 0.5rem; padding: 1rem; color: #064e3b;">'
                                    . '<strong>Gepaird</strong> op ' . e($record->paired_at?->format('d-m-Y H:i')) . '. '
                                    . 'Pi-hostname: <code>' . e($record->hostname ?? '?') . '</code>. '
                                    . 'Klik op "Opnieuw pairen" bovenaan als je het token wilt vervangen.'
                                    . '</div>'
                                );
                            }

                            return new HtmlString(
                                '<div style="background-color: #fef3c7; border-left: 4px solid #d97706; border-radius: 0.5rem; padding: 1rem; color: #78350f;">'
                                . '<strong>Wachten op pairing</strong>. Ga naar Print queue instellingen voor het installatie-commando.'
                                . '</div>'
                            );
                        }),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->whereNotNull('paired_at'))
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('hostname')->label('Hostname')->placeholder('-'),
                TextColumn::make('location'),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (PrinterType $state) => $state->label()),
                TextColumn::make('cups_name')->label('CUPS')->placeholder('-'),
                IconColumn::make('is_online')
                    ->label('Online')
                    ->getStateUsing(fn (Printer $r) => $r->isOnline())
                    ->boolean(),
                TextColumn::make('pending_jobs_count')
                    ->label('Wachtrij')
                    ->counts(['printJobs as pending_jobs_count' => fn ($q) => $q->where('status', 'pending')]),
                ToggleColumn::make('is_active'),
                TextColumn::make('last_ping_at')->since(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalDescription('Verwijdert de printer en trekt het token in. De daemon op de Pi/NAS zal vanaf nu 401 krijgen. Print jobs blijven bestaan (printer_id wordt null).'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrinters::route('/'),
            'edit' => Pages\EditPrinter::route('/{record}/edit'),
        ];
    }
}
