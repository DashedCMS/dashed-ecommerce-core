<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\URL;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Forms\Components\Placeholder;
use Dashed\DashedEcommerceCore\Models\Printer;
use Dashed\DashedEcommerceCore\Enums\PrinterType;
use Dashed\DashedEcommerceCore\Filament\Resources\PrinterResource\Pages;

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
                ->description('Geef de printer een eigen naam in het CMS, en vul de CUPS-naam in die op de Pi/NAS staat geconfigureerd.')
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')
                        ->label('Naam in CMS')
                        ->helperText('Vrij in te vullen, bijvoorbeeld "Lovora pakbon Brother".')
                        ->required()
                        ->maxLength(100),
                    TextInput::make('cups_name')
                        ->label('CUPS naam op de Pi/NAS')
                        ->helperText('Exact zoals "lpstat -p" op de Pi/NAS de printer toont. Bijvoorbeeld "pakbon_brother".')
                        ->required()
                        ->maxLength(80)
                        ->regex('/^[A-Za-z0-9_-]+$/')
                        ->validationMessages(['regex' => 'CUPS namen bevatten alleen letters, cijfers, _ en -.']),
                    Select::make('type')
                        ->label('Doel')
                        ->options(PrinterType::options())
                        ->required(),
                    TextInput::make('location')
                        ->label('Locatie')
                        ->helperText('Optioneel, bijvoorbeeld "magazijn" of "kassa".')
                        ->maxLength(100),
                    TextInput::make('max_retries')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(10)
                        ->default(3),
                    Toggle::make('is_active')
                        ->default(true),
                    Placeholder::make('last_ping_status')
                        ->label('Laatste ping')
                        ->content(fn (?Printer $record): string => $record?->last_ping_at
                            ? $record->last_ping_at->diffForHumans() . ' (' . ($record->isOnline() ? 'online' : 'offline') . ')'
                            : 'Nog niet gepingd'),
                ])
                ->columns(2),
            Section::make('Sanctum token + installatie-commando')
                ->description('Genereer een token (knop bovenaan) en kopieer daarna het commando om de daemon op je Pi/NAS te installeren.')
                ->columnSpanFull()
                ->visible(fn (?Printer $record): bool => $record !== null)
                ->schema([
                    Placeholder::make('token_and_install')
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->content(function (?Printer $record): HtmlString {
                            if (! $record) {
                                return new HtmlString('');
                            }

                            if (! $record->plain_token) {
                                return new HtmlString(
                                    '<div style="background-color: #fef3c7; border-left: 4px solid #d97706; border-radius: 0.5rem; padding: 1rem; color: #78350f;">'
                                    . '<strong>Nog geen token.</strong> Klik op de oranje "Genereer token" knop bovenaan deze pagina om er een aan te maken.'
                                    . '</div>'
                                );
                            }

                            $installUrl = URL::temporarySignedRoute(
                                'dashed.print-queue.installer',
                                now()->addHours(24),
                                ['ulid' => $record->ulid],
                            );
                            $oneLiner = 'curl -fsSL "' . $installUrl . '" | sudo bash';

                            return new HtmlString(
                                '<div style="display: flex; flex-direction: column; gap: 1rem;">'

                                . '<div>'
                                . '<div style="font-weight: 600; margin-bottom: 0.5rem;">1. Token (bewaar veilig, wordt ook in config.yaml op de Pi gezet):</div>'
                                . '<div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">'
                                . '<code style="background-color: #111827; color: #f3f4f6; padding: 0.5rem 0.75rem; border-radius: 0.375rem; font-family: ui-monospace, monospace; font-size: 0.8125rem; word-break: break-all; flex: 1; min-width: 0;">' . e($record->plain_token) . '</code>'
                                . '<button type="button" onclick="navigator.clipboard.writeText(\'' . e($record->plain_token) . '\'); this.textContent=\'Gekopieerd\'; setTimeout(()=>this.textContent=\'Kopieer\',1500);" style="background-color: #4f46e5; color: #ffffff; border: none; padding: 0.5rem 0.75rem; border-radius: 0.375rem; font-size: 0.8125rem; cursor: pointer; font-weight: 500;">Kopieer</button>'
                                . '</div>'
                                . '</div>'

                                . '<div>'
                                . '<div style="font-weight: 600; margin-bottom: 0.5rem;">2. Installatie-commando op je Pi of NAS (SSH erin, plak het commando, druk Enter):</div>'
                                . '<div style="display: flex; gap: 0.5rem; align-items: stretch; flex-wrap: wrap;">'
                                . '<code style="background-color: #111827; color: #f3f4f6; padding: 0.75rem; border-radius: 0.375rem; font-family: ui-monospace, monospace; font-size: 0.8125rem; word-break: break-all; flex: 1; min-width: 0; line-height: 1.4;">' . e($oneLiner) . '</code>'
                                . '<button type="button" onclick="navigator.clipboard.writeText(\'' . e($oneLiner) . '\'); this.textContent=\'Gekopieerd\'; setTimeout(()=>this.textContent=\'Kopieer commando\',1500);" style="background-color: #059669; color: #ffffff; border: none; padding: 0.5rem 1rem; border-radius: 0.375rem; font-size: 0.8125rem; cursor: pointer; font-weight: 500; white-space: nowrap;">Kopieer commando</button>'
                                . '</div>'
                                . '<p style="margin-top: 0.5rem; font-size: 0.8125rem; color: #4b5563;">'
                                . 'De link is 24 uur geldig. Het script installeert een kleine daemon die elke 5 seconden bij dit CMS langs gaat en jobs ophaalt voor CUPS-printer <code>' . e($record->cups_name ?? '?') . '</code>.'
                                . '</p>'
                                . '</div>'

                                . '<div style="background-color: #f3f4f6; border-radius: 0.5rem; padding: 0.75rem; color: #374151; font-size: 0.8125rem;">'
                                . '<strong>Vergeten welke CUPS-printers er op je Pi/NAS staan?</strong>'
                                . ' Run <code>lpstat -p</code> in een SSH-sessie naar het apparaat. De namen daar moeten matchen met het "CUPS naam"-veld hierboven.'
                                . '</div>'

                                . '</div>'
                            );
                        }),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('cups_name')->label('CUPS')->placeholder('-'),
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
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalDescription('Verwijdert de printer en trekt het token in. De daemon op de Pi/NAS zal vanaf nu 401 krijgen. Print jobs blijven bestaan (printer_id wordt null).'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->requiresConfirmation(),
                ]),
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
