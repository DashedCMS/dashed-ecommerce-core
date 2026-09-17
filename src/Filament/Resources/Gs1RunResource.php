<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Dashed\DashedEcommerceCore\Models\Gs1Run;
use Dashed\DashedEcommerceCore\Services\Gs1\Gs1Assigner;
use Dashed\DashedEcommerceCore\Filament\Resources\Gs1RunResource\Pages\ViewGs1Run;
use Dashed\DashedEcommerceCore\Filament\Resources\Gs1RunResource\Pages\ListGs1Runs;
use Dashed\DashedEcommerceCore\Filament\Resources\Gs1RunResource\RelationManagers\LinesRelationManager;

/**
 * Verwerkingen van mijnGS1-downloads. Geen menu-item: bereikbaar via de
 * knop GS1 op de productlijst.
 */
class Gs1RunResource extends Resource
{
    protected static ?string $model = Gs1Run::class;

    protected static bool $shouldRegisterNavigation = false;

    public static function getModelLabel(): string
    {
        return __('GS1-run');
    }

    public static function getPluralModelLabel(): string
    {
        return __('GS1-runs');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            Gs1Run::STATUS_CONCEPT => __('Concept'),
            Gs1Run::STATUS_TOEGEWEZEN => __('Toegewezen'),
            Gs1Run::STATUS_AFGESLOTEN => __('Afgesloten'),
            default => $status,
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label(__('Geüpload op'))->dateTime('d-m-Y H:i')->sortable(),
                TextColumn::make('user.name')->label(__('Door')),
                TextColumn::make('contract_sheet')->label(__('Contract')),
                TextColumn::make('status')->label(__('Status'))->badge()
                    ->formatStateUsing(fn (string $state) => static::statusLabel($state)),
                TextColumn::make('summary.pool')->label(__('Vrije codes')),
                TextColumn::make('summary.wees')->label(__('Weesgeraakt')),
                TextColumn::make('summary.assigned')->label(__('Toegewezen'))->placeholder('-'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Samenvatting'))
                ->columns(4)
                ->schema([
                    TextEntry::make('status')->label(__('Status'))->badge()
                        ->formatStateUsing(fn (string $state) => static::statusLabel($state)),
                    TextEntry::make('summary.naam_match')->label(__('Gekoppeld op naam'))->default(0),
                    TextEntry::make('summary.pool')->label(__('Vrije codes'))->default(0),
                    TextEntry::make('summary.wees')->label(__('Weesgeraakte codes'))->default(0),
                    TextEntry::make('summary.data_source')->label(__('Overgeslagen (GS1 Data Source)'))->default(0),
                    TextEntry::make('summary.assigned')->label(__('Toegewezen'))->placeholder('-'),
                    TextEntry::make('summary.skipped')->label(__('Overgeslagen bij toewijzen'))->placeholder('-'),
                    TextEntry::make('summary.file_rows')->label(__('Regels in uploadbestand'))->placeholder('-'),
                ]),
            Section::make(__('Voorbeeld'))
                ->description(__('Zo worden de codes verdeeld als je nu op Toewijzen klikt.'))
                ->visible(fn (Gs1Run $record) => $record->isConcept())
                ->schema([
                    ViewEntry::make('preview')
                        ->hiddenLabel()
                        ->state(fn (Gs1Run $record) => app(Gs1Assigner::class)->plan($record))
                        ->view('dashed-ecommerce-core::gs1.preview'),
                ]),
            Section::make(__('Let op bij het uploaden'))
                ->visible(fn (Gs1Run $record) => ! $record->isConcept() && filled($record->summary['multi_row_gtins'] ?? null))
                ->schema([
                    TextEntry::make('summary.multi_row_gtins')
                        ->label(__('Codes met meerdere landen of talen'))
                        ->helperText(__('Van deze codes staat alleen de eerste regel in het uploadbestand. De andere regels blijven bij GS1 ongewijzigd.'))
                        ->listWithLineBreaks(),
                ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [LinesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGs1Runs::route('/'),
            'view' => ViewGs1Run::route('/{record}'),
        ];
    }
}
