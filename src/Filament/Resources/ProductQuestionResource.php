<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Infolists\Components\TextEntry;
use Dashed\DashedEcommerceCore\Models\ProductQuestion;
use Dashed\DashedCore\Classes\Actions\ActionGroups\ToolbarActions;
use Dashed\DashedEcommerceCore\Services\ProductReviews\ProductQuestionService;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductQuestionResource\Pages\EditProductQuestion;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductQuestionResource\Pages\ListProductQuestions;

/**
 * Productvragen van bezoekers. "Beantwoorden" mailt de vraagsteller en zet
 * de vraag, als je dat aan laat staan, op de productpagina.
 */
class ProductQuestionResource extends Resource
{
    use \Dashed\DashedCore\Filament\Concerns\TranslatesResourceLabels;

    protected static ?string $model = ProductQuestion::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-question-mark-circle';
    protected static UnitEnum|string|null $navigationGroup = 'E-commerce';
    protected static ?string $navigationLabel = 'Productvragen';
    protected static ?string $label = 'Productvraag';
    protected static ?string $pluralLabel = 'Productvragen';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = rescue(fn () => ProductQuestion::query()->whereNull('answered_at')->where('status', '!=', ProductQuestion::STATUS_REJECTED)->count(), 0, false);

        return $count > 0 ? (string) $count : null;
    }

    public static function productName(ProductQuestion $record): string
    {
        $name = $record->product?->name ?? $record->productGroup?->name;

        return is_array($name) ? (string) (reset($name) ?: '') : (string) $name;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make(__('Vraag'))->columnSpanFull()->columns(2)->schema([
                TextEntry::make('product_info')
                    ->label(__('Product'))
                    ->state(fn (?ProductQuestion $record) => $record ? self::productName($record) : ''),
                TextEntry::make('email')
                    ->label(__('E-mailadres')),
                TextInput::make('name')
                    ->label(__('Naam'))
                    ->required()
                    ->maxLength(80),
                Select::make('status')
                    ->label(__('Status'))
                    ->options(ProductQuestion::statuses())
                    ->helperText(__('Alleen beantwoorde vragen met "Tonen op productpagina" staan op de site.'))
                    ->required(),
                Textarea::make('question')
                    ->label(__('Vraag'))
                    ->required()
                    ->rows(3)
                    ->columnSpanFull(),
                Textarea::make('answer')
                    ->label(__('Antwoord'))
                    ->helperText(__('Opslaan mailt de klant niet; gebruik daarvoor de actie Beantwoorden in de lijst.'))
                    ->rows(5)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['product', 'productGroup']))
            ->columns([
                TextColumn::make('product_name')
                    ->label(__('Product'))
                    ->state(fn (ProductQuestion $record) => self::productName($record))
                    ->wrap(),
                TextColumn::make('question')
                    ->label(__('Vraag'))
                    ->limit(100)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('name')
                    ->label(__('Naam'))
                    ->description(fn (ProductQuestion $record) => $record->email)
                    ->searchable(['name', 'email']),
                TextColumn::make('answered_at')
                    ->label(__('Beantwoord op'))
                    ->dateTime('d-m-Y H:i')
                    ->placeholder(__('Nog niet'))
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => ProductQuestion::statuses()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        ProductQuestion::STATUS_APPROVED => 'success',
                        ProductQuestion::STATUS_REJECTED => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('created_at')
                    ->label(__('Gesteld op'))
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(ProductQuestion::statuses()),
                TernaryFilter::make('answered_at')
                    ->label(__('Beantwoord'))
                    ->placeholder(__('Alles'))
                    ->trueLabel(__('Beantwoord'))
                    ->falseLabel(__('Nog niet beantwoord'))
                    ->nullable(),
            ])
            ->recordActions([
                Action::make('answer')
                    ->label(__('Beantwoorden'))
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('primary')
                    ->authorize(fn (ProductQuestion $record) => auth()->user()?->can('update', $record))
                    ->fillForm(fn (ProductQuestion $record) => [
                        'answer' => $record->answer,
                        'show' => $record->status !== ProductQuestion::STATUS_REJECTED,
                        'notify' => ! $record->customer_notified_at,
                    ])
                    ->schema([
                        TextEntry::make('question_text')
                            ->label(__('Vraag'))
                            ->state(fn (ProductQuestion $record) => $record->question),
                        Textarea::make('answer')
                            ->label(__('Antwoord'))
                            ->required()
                            ->rows(6),
                        Toggle::make('show')
                            ->label(__('Tonen op productpagina')),
                        Toggle::make('notify')
                            ->label(__('Antwoord mailen naar de klant')),
                    ])
                    ->action(function (ProductQuestion $record, array $data): void {
                        ProductQuestionService::answer($record, (string) $data['answer'], (bool) ($data['show'] ?? false), (bool) ($data['notify'] ?? false), auth()->id());
                        Notification::make()->title(__('Antwoord opgeslagen'))->success()->send();
                    }),
                Action::make('reject')
                    ->label(__('Afwijzen'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (ProductQuestion $record) => $record->status !== ProductQuestion::STATUS_REJECTED)
                    ->authorize(fn (ProductQuestion $record) => auth()->user()?->can('update', $record))
                    ->requiresConfirmation()
                    ->action(fn (ProductQuestion $record) => $record->forceFill(['status' => ProductQuestion::STATUS_REJECTED])->save()),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions(ToolbarActions::getActions());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductQuestions::route('/'),
            'edit' => EditProductQuestion::route('/{record}/edit'),
        ];
    }
}
