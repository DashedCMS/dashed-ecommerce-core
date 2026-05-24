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
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
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
