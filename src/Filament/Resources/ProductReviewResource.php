<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Tables\Filters\SelectFilter;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Database\Eloquent\Collection;
use Dashed\DashedEcommerceCore\Models\ProductReview;
use Dashed\DashedCore\Classes\Actions\ActionGroups\ToolbarActions;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductReviewResource\Pages\EditProductReview;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductReviewResource\Pages\ListProductReviews;

/**
 * Moderatie van de eigen productreviews: goedkeuren, afwijzen en een
 * antwoord van de winkel. Alleen goedgekeurde reviews staan op de site en
 * tellen mee in het productschema.
 */
class ProductReviewResource extends Resource
{
    use \Dashed\DashedCore\Filament\Concerns\TranslatesResourceLabels;

    protected static ?string $model = ProductReview::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-star';
    protected static UnitEnum|string|null $navigationGroup = 'E-commerce';
    protected static ?string $navigationLabel = 'Productreviews';
    protected static ?string $label = 'Productreview';
    protected static ?string $pluralLabel = 'Productreviews';

    public static function canCreate(): bool
    {
        // Reviews komen van kopers via het reviewverzoek, niet uit het CMS.
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = rescue(fn () => ProductReview::query()->where('status', ProductReview::STATUS_PENDING)->count(), 0, false);

        return $count > 0 ? (string) $count : null;
    }

    public static function productName(ProductReview $record): string
    {
        $name = $record->product?->name ?? $record->productGroup?->name;

        return is_array($name) ? (string) (reset($name) ?: '') : (string) $name;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make(__('Review'))->columnSpanFull()->columns(2)->schema([
                TextEntry::make('product_info')
                    ->label(__('Product'))
                    ->state(fn (?ProductReview $record) => $record ? self::productName($record) : ''),
                TextEntry::make('order_info')
                    ->label(__('Bestelling'))
                    ->state(fn (?ProductReview $record) => $record?->order?->invoice_id ?: __('Geen'))
                    ->helperText(fn (?ProductReview $record) => $record?->verified_buyer ? __('Geverifieerde koper') : null),
                Select::make('rating')
                    ->label(__('Sterren'))
                    ->options([5 => '5', 4 => '4', 3 => '3', 2 => '2', 1 => '1'])
                    ->required(),
                Select::make('status')
                    ->label(__('Status'))
                    ->options(ProductReview::statuses())
                    ->required(),
                TextInput::make('name')
                    ->label(__('Naam'))
                    ->required()
                    ->maxLength(80),
                TextInput::make('title')
                    ->label(__('Titel'))
                    ->maxLength(120),
                Textarea::make('body')
                    ->label(__('Tekst'))
                    ->rows(5)
                    ->columnSpanFull(),
            ]),
            Section::make(__('Antwoord van de winkel'))->columnSpanFull()->schema([
                Textarea::make('reply')
                    ->label(__('Antwoord'))
                    ->helperText(__('Staat onder de review op de productpagina, zodra de review is goedgekeurd.'))
                    ->rows(4),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['product', 'productGroup', 'order']))
            ->columns([
                TextColumn::make('product_name')
                    ->label(__('Product'))
                    ->state(fn (ProductReview $record) => self::productName($record))
                    ->wrap(),
                TextColumn::make('rating')
                    ->label(__('Sterren'))
                    ->formatStateUsing(fn ($state) => str_repeat('★', (int) $state) . str_repeat('☆', 5 - (int) $state))
                    ->sortable(),
                TextColumn::make('title')
                    ->label(__('Review'))
                    ->state(fn (ProductReview $record) => $record->title ?: mb_strimwidth((string) $record->body, 0, 80, '...'))
                    ->description(fn (ProductReview $record) => $record->title ? mb_strimwidth((string) $record->body, 0, 120, '...') : null)
                    ->wrap()
                    ->searchable(['title', 'body']),
                TextColumn::make('name')
                    ->label(__('Naam'))
                    ->searchable(),
                IconColumn::make('verified_buyer')
                    ->label(__('Koper'))
                    ->boolean(),
                IconColumn::make('reply')
                    ->label(__('Beantwoord'))
                    ->state(fn (ProductReview $record) => filled($record->reply))
                    ->boolean(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => ProductReview::statuses()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        ProductReview::STATUS_APPROVED => 'success',
                        ProductReview::STATUS_REJECTED => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('created_at')
                    ->label(__('Geplaatst op'))
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(ProductReview::statuses()),
                SelectFilter::make('rating')
                    ->label(__('Sterren'))
                    ->options([5 => '5', 4 => '4', 3 => '3', 2 => '2', 1 => '1']),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label(__('Goedkeuren'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (ProductReview $record) => $record->status !== ProductReview::STATUS_APPROVED)
                    ->authorize(fn (ProductReview $record) => auth()->user()?->can('update', $record))
                    ->action(function (ProductReview $record): void {
                        $record->approve();
                        Notification::make()->title(__('Review goedgekeurd'))->success()->send();
                    }),
                Action::make('reject')
                    ->label(__('Afwijzen'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (ProductReview $record) => $record->status !== ProductReview::STATUS_REJECTED)
                    ->authorize(fn (ProductReview $record) => auth()->user()?->can('update', $record))
                    ->requiresConfirmation()
                    ->action(function (ProductReview $record): void {
                        $record->reject();
                        Notification::make()->title(__('Review afgewezen'))->success()->send();
                    }),
                Action::make('reply')
                    ->label(__('Antwoorden'))
                    ->icon('heroicon-o-chat-bubble-left')
                    ->authorize(fn (ProductReview $record) => auth()->user()?->can('update', $record))
                    ->fillForm(fn (ProductReview $record) => ['reply' => $record->reply])
                    ->schema([
                        Textarea::make('reply')
                            ->label(__('Antwoord van de winkel'))
                            ->helperText(__('Staat onder de review op de productpagina, zodra de review is goedgekeurd.'))
                            ->rows(5),
                    ])
                    ->action(function (ProductReview $record, array $data): void {
                        self::saveReply($record, $data['reply'] ?? null);
                        Notification::make()->title(__('Antwoord opgeslagen'))->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions(array_merge([
                BulkAction::make('approveSelected')
                    ->label(__('Goedkeuren'))
                    ->icon('heroicon-o-check')
                    ->authorize(fn () => auth()->user()?->can('edit_product_review'))
                    ->action(fn (Collection $records) => $records->each->approve())
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('rejectSelected')
                    ->label(__('Afwijzen'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize(fn () => auth()->user()?->can('edit_product_review'))
                    ->action(fn (Collection $records) => $records->each->reject())
                    ->deselectRecordsAfterCompletion(),
            ], ToolbarActions::getActions()));
    }

    public static function saveReply(ProductReview $record, ?string $reply): void
    {
        $reply = trim((string) $reply);

        $record->forceFill([
            'reply' => $reply === '' ? null : $reply,
            'replied_at' => $reply === '' ? null : now(),
            'replied_by' => $reply === '' ? null : auth()->id(),
        ])->save();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductReviews::route('/'),
            'edit' => EditProductReview::route('/{record}/edit'),
        ];
    }
}
