<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderHandledFlowResource\Widgets;

use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedEcommerceCore\Models\OrderFlowEnrollment;

/**
 * Lijst onderaan de flow edit-pagina met alle orders die in deze flow zitten
 * (of zaten). Sortable + filterbaar op status / annuleer-reden.
 */
class OrderHandledFlowEnrollments extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Inschrijvingen';

    public ?Model $record = null;

    public function table(Table $table): Table
    {
        $flowId = $this->record?->id;

        return $table
            ->query(function () use ($flowId): Builder {
                $query = OrderFlowEnrollment::query()->with(['order']);

                if ($flowId) {
                    $query->where('flow_id', $flowId);
                } else {
                    $query->whereRaw('1 = 0');
                }

                return $query;
            })
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Nog geen inschrijvingen')
            ->emptyStateDescription('Zodra een bestelling in de gekozen fulfillment-status komt verschijnt die hier.')
            ->columns([
                TextColumn::make('order.invoice_id')
                    ->label('Bestelling')
                    ->url(fn (OrderFlowEnrollment $record): ?string => $record->order
                        ? route('filament.dashed.resources.orders.edit', ['record' => $record->order_id])
                        : null)
                    ->openUrlInNewTab()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order.name')
                    ->label('Klant')
                    ->wrap(),
                TextColumn::make('chosen_review_url_label')
                    ->label('Platform')
                    ->placeholder('-')
                    ->badge()
                    ->color(fn (?string $state): string => self::platformBadgeColor($state))
                    ->toggleable(),
                TextColumn::make('order.email')
                    ->label('E-mail')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('started_at')
                    ->label('Ingeschreven op')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
                IconColumn::make('cancelled_at')
                    ->label('Actief')
                    ->boolean()
                    ->trueIcon('heroicon-o-x-circle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('warning')
                    ->falseColor('success')
                    ->getStateUsing(fn (OrderFlowEnrollment $record): bool => $record->cancelled_at !== null)
                    ->tooltip(fn (OrderFlowEnrollment $record): string => $record->cancelled_at
                        ? 'Geannuleerd op ' . $record->cancelled_at->format('d-m-Y H:i')
                        : 'Loopt nog'),
                TextColumn::make('cancelled_reason')
                    ->label('Reden')
                    ->placeholder('-')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'link_click' => 'Klik op link',
                        'unsubscribed_via_link' => 'Afgemeld via link',
                        'recent_paid_order' => 'Recent betaalde order',
                        'mail_failed' => 'Mail mislukt',
                        'migrated' => 'Gemigreerd',
                        null, '' => '-',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'link_click', 'unsubscribed_via_link' => 'info',
                        'recent_paid_order' => 'success',
                        'mail_failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('cancelled_at')
                    ->label('Geannuleerd op')
                    ->dateTime('d-m-Y H:i')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('actief')
                    ->label('Alleen actieve')
                    ->query(fn (Builder $query): Builder => $query->whereNull('cancelled_at')),
                SelectFilter::make('cancelled_reason')
                    ->label('Annuleer-reden')
                    ->options([
                        'link_click' => 'Klik op link',
                        'unsubscribed_via_link' => 'Afgemeld via link',
                        'recent_paid_order' => 'Recent betaalde order',
                        'mail_failed' => 'Mail mislukt',
                        'migrated' => 'Gemigreerd',
                    ]),
                SelectFilter::make('chosen_review_url_label')
                    ->label('Platform')
                    ->options(function (): array {
                        if (! $this->record?->id) {
                            return [];
                        }

                        return OrderFlowEnrollment::query()
                            ->where('flow_id', $this->record->id)
                            ->whereNotNull('chosen_review_url_label')
                            ->distinct()
                            ->pluck('chosen_review_url_label', 'chosen_review_url_label')
                            ->toArray();
                    }),
            ])
            ->defaultSort('started_at', 'desc');
    }

    /**
     * Stabiele hash-gebaseerde kleur per platform-label, zodat Google/KiyOh/etc.
     * altijd dezelfde badge-kleur krijgen.
     */
    protected static function platformBadgeColor(?string $state): string
    {
        if ($state === null || $state === '') {
            return 'gray';
        }

        $palette = ['primary', 'success', 'warning', 'info', 'danger'];
        $index = abs(crc32($state)) % count($palette);

        return $palette[$index];
    }
}
