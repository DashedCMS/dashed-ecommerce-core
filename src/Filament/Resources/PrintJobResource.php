<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Collection;
use Dashed\DashedEcommerceCore\Models\PrintJob;
use Dashed\DashedEcommerceCore\Enums\PrintJobType;
use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;
use Dashed\DashedEcommerceCore\Filament\Resources\PrintJobResource\Pages;

class PrintJobResource extends Resource
{
    protected static ?string $model = PrintJob::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static string|UnitEnum|null $navigationGroup = 'Print queue';

    protected static ?string $navigationLabel = 'Wachtrij';

    protected static ?string $modelLabel = 'Print job';

    protected static ?string $pluralModelLabel = 'Print jobs';

    public static function table(Table $table): Table
    {
        return $table
            ->poll('5s')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('ulid')->limit(8)->tooltip(fn ($state) => $state),
                TextColumn::make('type')->badge()->formatStateUsing(fn (PrintJobType $s) => $s->label()),
                TextColumn::make('order.invoice_id')
                    ->label('Bestelling')
                    ->url(fn (PrintJob $r) => $r->order
                        ? route('filament.dashed.resources.orders.view', $r->order_id)
                        : null),
                TextColumn::make('printer.name')->label('Printer')->placeholder('-'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (PrintJobStatus $s) => $s->color())
                    ->formatStateUsing(fn (PrintJobStatus $s) => $s->label()),
                TextColumn::make('attempts'),
                TextColumn::make('created_at')->since(),
                TextColumn::make('printed_at')->since()->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')->multiple()->options(
                    collect(PrintJobStatus::cases())
                        ->mapWithKeys(fn (PrintJobStatus $s) => [$s->value => $s->label()])
                        ->all()
                ),
                SelectFilter::make('type')->options(
                    collect(PrintJobType::cases())
                        ->mapWithKeys(fn (PrintJobType $t) => [$t->value => $t->label()])
                        ->all()
                ),
                SelectFilter::make('printer_id')->relationship('printer', 'name'),
            ])
            ->recordActions([
                Action::make('retry')
                    ->visible(fn (PrintJob $r) => $r->status === PrintJobStatus::Failed)
                    ->color('warning')
                    ->action(fn (PrintJob $r) => $r->retry()),
                Action::make('cancel')
                    ->visible(fn (PrintJob $r) => in_array($r->status, [PrintJobStatus::Pending, PrintJobStatus::Claimed], true))
                    ->color('danger')
                    ->action(fn (PrintJob $r) => $r->update(['status' => PrintJobStatus::Cancelled])),
                Action::make('view_order')
                    ->visible(fn (PrintJob $r) => (bool) $r->order_id)
                    ->url(fn (PrintJob $r) => route('filament.dashed.resources.orders.view', $r->order_id)),
            ])
            ->toolbarActions([
                BulkAction::make('retry_bulk')
                    ->label('Opnieuw proberen')
                    ->action(fn (Collection $records) => $records
                        ->filter(fn (PrintJob $j) => $j->status === PrintJobStatus::Failed)
                        ->each(fn (PrintJob $j) => $j->retry())),
                BulkAction::make('cancel_bulk')
                    ->label('Annuleren')
                    ->action(fn (Collection $records) => $records->each(
                        fn (PrintJob $j) => $j->update(['status' => PrintJobStatus::Cancelled])
                    )),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrintJobs::route('/'),
            'view' => Pages\ViewPrintJob::route('/{record}'),
        ];
    }
}
