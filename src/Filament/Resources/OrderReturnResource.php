<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\RichEditor;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Filters\SelectFilter;
use Dashed\DashedCore\Models\EmailTemplate;
use Filament\Infolists\Components\TextEntry;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Filament\Infolists\Components\RepeatableEntry;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnCustomMail;

class OrderReturnResource extends Resource
{
    protected static ?string $model = OrderReturn::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static string|UnitEnum|null $navigationGroup = 'Retouren';

    protected static ?string $navigationLabel = 'Retouren';

    protected static ?string $label = 'Retouraanvraag';

    protected static ?string $pluralLabel = 'Retouraanvragen';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $count = OrderReturn::notHandled()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['order', 'lines.orderProduct', 'lines.returnReason']))
            ->defaultSort('requested_at', 'desc')
            ->columns([
                TextColumn::make('order.invoice_id')
                    ->label('Bestelling')
                    ->formatStateUsing(fn ($state, $record) => $state ?: ('#' . $record->order_id))
                    ->url(fn ($record) => $record->order_id ? \Dashed\DashedEcommerceCore\Filament\Resources\OrderResource::getUrl('edit', ['record' => $record->order_id]) : null),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => OrderReturn::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        OrderReturn::STATUS_REQUESTED => 'warning',
                        OrderReturn::STATUS_APPROVED => 'success',
                        OrderReturn::STATUS_REJECTED => 'danger',
                        OrderReturn::STATUS_HANDLED => 'gray',
                        default => 'gray',
                    }),
                IconColumn::make('auto_accepted')
                    ->label('Automatisch')
                    ->boolean(),
                TextColumn::make('requested_at')
                    ->label('Aangevraagd op')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
                TextColumn::make('lines_count')
                    ->label('Regels')
                    ->counts('lines'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(OrderReturn::statusLabels()),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('approve')
                    ->label('Goedkeuren')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === OrderReturn::STATUS_REQUESTED)
                    ->schema([
                        Textarea::make('admin_note')
                            ->label('Notitie (optioneel)'),
                    ])
                    ->action(function ($record, $data) {
                        $record->approve($data['admin_note'] ?? null);

                        Notification::make()
                            ->success()
                            ->title('Retouraanvraag goedgekeurd')
                            ->send();
                    }),
                Action::make('reject')
                    ->label('Afkeuren')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === OrderReturn::STATUS_REQUESTED)
                    ->schema([
                        Textarea::make('rejected_reason')
                            ->label('Reden')
                            ->required(),
                    ])
                    ->action(function ($record, $data) {
                        $record->reject($data['rejected_reason']);

                        Notification::make()
                            ->success()
                            ->title('Retouraanvraag afgekeurd')
                            ->send();
                    }),
                Action::make('markHandled')
                    ->label('Markeer als afgehandeld')
                    ->color('gray')
                    ->visible(fn ($record) => in_array($record->status, [OrderReturn::STATUS_REQUESTED, OrderReturn::STATUS_APPROVED]))
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->markHandled();

                        Notification::make()
                            ->success()
                            ->title('Retouraanvraag gemarkeerd als afgehandeld')
                            ->send();
                    }),
                Action::make('sendEmail')
                    ->label('Stuur e-mail')
                    ->icon('heroicon-o-envelope')
                    ->color('primary')
                    ->schema([
                        TextInput::make('email')
                            ->label('E-mailadres')
                            ->email()
                            ->required()
                            ->default(fn ($record) => $record->email),
                        TextInput::make('subject')
                            ->label('Onderwerp')
                            ->required()
                            ->default(function () {
                                $template = EmailTemplate::forMailable(OrderReturnCustomMail::emailTemplateKey());

                                return $template?->getTranslation('subject', app()->getLocale(), useFallbackLocale: true)
                                    ?: OrderReturnCustomMail::defaultSubject();
                            }),
                        RichEditor::make('message')
                            ->label('Bericht')
                            ->required()
                            ->default(fn () => OrderReturnCustomMail::defaultMessage()),
                    ])
                    ->action(function ($record, array $data) {
                        $record->sendCustomEmail($data['subject'], $data['message'], $data['email']);

                        Notification::make()
                            ->success()
                            ->title('Bericht naar klant verstuurd')
                            ->send();
                    }),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Fieldset::make('Retouraanvraag')
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('order.invoice_id')
                        ->label('Bestelling')
                        ->formatStateUsing(fn ($state, $record) => $state ?: ('#' . $record->order_id)),
                    TextEntry::make('email')
                        ->label('E-mail'),
                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn ($state) => OrderReturn::statusLabels()[$state] ?? $state),
                    TextEntry::make('requested_at')
                        ->label('Aangevraagd op')
                        ->dateTime('d-m-Y H:i'),
                    TextEntry::make('auto_accepted')
                        ->label('Automatisch goedgekeurd')
                        ->formatStateUsing(fn ($state) => $state ? 'Ja' : 'Nee'),
                    TextEntry::make('return_label_provider')
                        ->label('Retourlabel via')
                        ->placeholder('-'),
                ]),
            RepeatableEntry::make('lines')
                ->label('Geretourneerde producten')
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('orderProduct.name')
                        ->label('Product'),
                    TextEntry::make('quantity')
                        ->label('Aantal'),
                    TextEntry::make('returnReason.label')
                        ->label('Reden')
                        ->formatStateUsing(fn ($state) => is_array($state) ? ($state[app()->getLocale()] ?? reset($state)) : $state),
                    TextEntry::make('reason_note')
                        ->label('Toelichting')
                        ->default('-'),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => OrderReturnResource\Pages\ListOrderReturns::route('/'),
            'view' => OrderReturnResource\Pages\ViewOrderReturn::route('/{record}'),
        ];
    }
}
