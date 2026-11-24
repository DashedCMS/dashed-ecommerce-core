<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\CartResource\RelationManagers;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Filament\Resources\RelationManagers\RelationManager;
use Dashed\DashedEcommerceCore\Models\CheckoutAbandonment;
use Dashed\DashedEcommerceCore\Filament\Pages\Statistics\CheckoutAbandonmentsPage;

/**
 * Why this cart did not get through the checkout: the failed fields with the
 * rejected value, number of attempts, and other pre-order abandonment reasons.
 */
class CheckoutAbandonmentsRelationManager extends RelationManager
{
    use \Dashed\DashedCore\Filament\Concerns\TranslatesRelationManagerLabels;

    protected static string $relationship = 'checkoutAbandonments';

    protected static ?string $title = 'Checkoutfouten';

    protected static ?string $modelLabel = 'checkoutfout';

    protected static ?string $pluralModelLabel = 'checkoutfouten';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->checkoutAbandonments()->count();

        return $count ? (string) $count : null;
    }

    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        return 'danger';
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('updated_at')
                    ->label(__('Laatste poging'))
                    ->dateTime('d-m-Y H:i:s', 'Europe/Amsterdam')
                    ->description(fn (CheckoutAbandonment $record) => __('Eerste: ') . $record->created_at?->timezone('Europe/Amsterdam')->format('d-m-Y H:i'))
                    ->sortable(),
                TextColumn::make('reason')
                    ->label(__('Reden'))
                    ->badge()
                    ->color('danger')
                    ->formatStateUsing(fn (string $state) => CheckoutAbandonmentsPage::reasonLabels()[$state] ?? $state),
                TextColumn::make('attempts')
                    ->label(__('Pogingen'))
                    ->state(fn (CheckoutAbandonment $record) => $record->attempts()),
                TextColumn::make('errors')
                    ->label(__('Fouten'))
                    ->state(fn (CheckoutAbandonment $record) => $record->errorLines() ?: ['-'])
                    ->listWithLineBreaks()
                    ->wrap(),
                TextColumn::make('email')
                    ->label(__('E-mail'))
                    ->toggleable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->paginated([25, 50])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
