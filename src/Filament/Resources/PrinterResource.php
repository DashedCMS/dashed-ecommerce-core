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
                        ->options(PrinterType::options())
                        ->required(),
                    TextInput::make('max_retries')->numeric()->minValue(0)->maxValue(10)->default(3),
                    Toggle::make('is_active')->default(true),
                    Placeholder::make('last_ping_status')
                        ->label('Laatste ping')
                        ->content(fn (?Printer $record): string => $record?->last_ping_at
                            ? $record->last_ping_at->diffForHumans() . ' (' . ($record->isOnline() ? 'online' : 'offline') . ')'
                            : 'Nog niet gepingd'),
                ])
                ->columns(2),
            Section::make('Sanctum token')
                ->description('Gegenereerd via de "Genereer token" knop boven op de pagina. Dit token gebruikt de Raspberry Pi om in te loggen.')
                ->columnSpanFull()
                ->visible(fn (?Printer $record) => $record !== null)
                ->schema([
                    Placeholder::make('plain_token_display')
                        ->label('Huidige token')
                        ->columnSpanFull()
                        ->content(fn (?Printer $record): HtmlString => new HtmlString(
                            $record?->plain_token
                                ? '<div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">'
                                    . '<code style="background-color: #111827; color: #f3f4f6; padding: 0.5rem 0.75rem; border-radius: 0.375rem; font-family: ui-monospace, monospace; font-size: 0.8125rem; word-break: break-all; flex: 1; min-width: 0;">'
                                    . e($record->plain_token)
                                    . '</code>'
                                    . '<button type="button" onclick="navigator.clipboard.writeText(\'' . e($record->plain_token) . '\'); this.textContent = \'Gekopieerd\'; setTimeout(() => this.textContent = \'Kopieer\', 1500);" style="background-color: #4f46e5; color: #ffffff; border: none; padding: 0.5rem 0.75rem; border-radius: 0.375rem; font-size: 0.8125rem; cursor: pointer; font-weight: 500;">Kopieer</button>'
                                    . '</div>'
                                : '<em style="color: #6b7280;">Nog geen token gegenereerd. Klik op "Genereer token" hierboven.</em>'
                        )),
                ]),
            Section::make('Installatie op een Raspberry Pi')
                ->description('Stap-voor-stap instructies, helemaal pre-filled met de gegevens van deze printer.')
                ->columnSpanFull()
                ->collapsible()
                ->collapsed(fn (?Printer $record) => $record === null || ! $record->plain_token)
                ->visible(fn (?Printer $record) => $record !== null)
                ->schema([
                    Placeholder::make('pi_setup_for_printer')
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->content(fn (?Printer $record): HtmlString => new HtmlString(
                            view('dashed-ecommerce-core::filament.pages.print-queue-pi-setup-printer', [
                                'printer' => $record,
                            ])->render()
                        )),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('location'),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (PrinterType $state) => $state->label()),
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
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrinters::route('/'),
            'create' => Pages\CreatePrinter::route('/create'),
            'edit' => Pages\EditPrinter::route('/{record}/edit'),
        ];
    }
}
