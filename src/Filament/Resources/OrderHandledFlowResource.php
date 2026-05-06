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
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Mail;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\RichEditor;
use Dashed\DashedEcommerceCore\Classes\Orders;
use Dashed\DashedEcommerceCore\Mail\OrderHandledMail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderHandledFlow;
use Dashed\DashedEcommerceCore\Models\OrderHandledFlowStep;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderHandledFlowResource\Pages\EditOrderHandledFlow;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderHandledFlowResource\Pages\ListOrderHandledFlows;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderHandledFlowResource\Pages\CreateOrderHandledFlow;

class OrderHandledFlowResource extends Resource
{
    public const VARIABLES_HELP = 'Variabelen: :siteName: :siteUrl: :orderNumber: :customerName: :firstName: :discountCode: :discountValue: :reviewUrl:';

    protected static ?string $model = OrderHandledFlow::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope-open';

    protected static string|UnitEnum|null $navigationGroup = 'E-commerce';

    protected static ?string $label = 'Order opvolg flow';

    protected static ?string $pluralLabel = 'Order opvolg flows';

    protected static ?string $navigationLabel = 'Order opvolg flows';

    protected static ?int $navigationSort = 56;

    public static function getNavigationLabel(): string
    {
        return static::$navigationLabel ?? 'Order opvolg flows';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Algemeen')
                ->schema([
                    TextInput::make('name')
                        ->label('Naam')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Select::make('trigger_status')
                        ->label('Trigger status')
                        ->helperText('De flow start zodra de bestelling deze fulfillment-status krijgt.')
                        ->options(Orders::getFulfillmentStatusses())
                        ->default('handled')
                        ->required()
                        ->native(false),
                    Toggle::make('is_active')
                        ->label('Actieve flow')
                        ->default(true)
                        ->helperText('Per trigger-status kan maximaal 1 flow actief zijn. Een nieuwe actieve flow op dezelfde trigger-status zet de vorige automatisch op inactive.'),
                    Toggle::make('cancel_on_link_click')
                        ->label('Annuleer flow bij klik')
                        ->default(true)
                        ->helperText('Wanneer een ontvanger op een knop of afbeelding-link in de mail klikt, worden eventuele volgende stappen voor deze bestelling overgeslagen.'),
                    TextInput::make('discount_prefix')
                        ->label('Kortingscode prefix')
                        ->helperText('Optioneel. Wordt gebruikt wanneer een stap een kortingsblok bevat zonder eigen code.')
                        ->maxLength(20),
                    TextInput::make('skip_if_recently_ordered_within_days')
                        ->label('Skip bij recente bestelling (dagen)')
                        ->helperText('Geen mail versturen wanneer dezelfde klant in de afgelopen X dagen al een nieuwe betaalde bestelling heeft geplaatst. Leeg of 0 zet de check uit.')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(365)
                        ->default(30),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('Opvolg-stappen')
                ->description('Voeg de mails toe die in volgorde verstuurd worden nadat een bestelling de geconfigureerde fulfillment-status krijgt.')
                ->schema([
                    Repeater::make('steps')
                        ->relationship()
                        ->mutateRelationshipDataBeforeFillUsing(static function (array $data): array {
                            $locale = app()->getLocale();
                            foreach (['subject', 'blocks'] as $field) {
                                if (! array_key_exists($field, $data)) {
                                    continue;
                                }
                                $value = $data[$field];
                                if (is_array($value) && ! array_is_list($value)) {
                                    $value = $value[$locale] ?? null;
                                }
                                if ($field === 'blocks') {
                                    $data[$field] = is_array($value) ? array_values($value) : [];
                                } else {
                                    $data[$field] = is_string($value) ? $value : '';
                                }
                            }

                            return $data;
                        })
                        ->orderColumn('sort_order')
                        ->defaultItems(1)
                        ->addActionLabel('Stap toevoegen')
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->extraItemActions([
                            Action::make('sendTestMail')
                                ->label('Test mail naar mij sturen')
                                ->icon('heroicon-o-paper-airplane')
                                ->color('info')
                                ->modalHeading('Test mail versturen')
                                ->modalDescription('Verstuurt een synchrone test-render van deze stap naar het opgegeven adres. Werkt ook voor nog niet opgeslagen wijzigingen.')
                                ->modalSubmitActionLabel('Versturen')
                                ->form([
                                    TextInput::make('recipient')
                                        ->label('Ontvanger')
                                        ->email()
                                        ->required()
                                        ->default(fn () => auth()->user()?->email),
                                ])
                                ->action(function (array $arguments, array $data, Repeater $component): void {
                                    $itemState = $component->getRawItemState($arguments['item']);
                                    $locale = app()->getLocale();
                                    $recipient = (string) ($data['recipient'] ?? '');

                                    $blocks = $itemState['blocks'] ?? [];
                                    if (! is_array($blocks)) {
                                        $blocks = [];
                                    }
                                    $blocks = array_values($blocks);

                                    $step = new OrderHandledFlowStep();
                                    $step->send_after_minutes = (int) ($itemState['send_after_minutes'] ?? 20160);
                                    $step->is_active = (bool) ($itemState['is_active'] ?? true);
                                    $step->setTranslation('subject', $locale, (string) ($itemState['subject'] ?? 'Test mail'));
                                    $step->setTranslation('blocks', $locale, $blocks);

                                    $order = Order::query()->latest()->first() ?? new Order();
                                    if (! $order->exists) {
                                        $order->first_name = 'Test';
                                        $order->last_name = 'Klant';
                                        $order->email = $recipient;
                                        $order->invoice_id = 'PREVIEW';
                                    }

                                    $mailable = new OrderHandledMail($order, $step, $locale);
                                    $mailable->previewDiscountCode = 'PREVIEW-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
                                    $mailable->previewDiscountValue = '10%';

                                    try {
                                        Mail::to($recipient)->sendNow($mailable);

                                        Notification::make()
                                            ->title('Test mail verstuurd naar '.$recipient)
                                            ->success()
                                            ->send();
                                    } catch (\Throwable $e) {
                                        report($e);
                                        Notification::make()
                                            ->title('Test mail mislukt')
                                            ->body($e->getMessage())
                                            ->danger()
                                            ->send();
                                    }
                                }),
                        ])
                        ->itemLabel(function (array $state): ?string {
                            $minutes = (int) ($state['send_after_minutes'] ?? 0);
                            $label = static::formatDelayLabel($minutes);
                            $subject = $state['subject'] ?? null;
                            if (is_array($subject)) {
                                $subject = $subject[app()->getLocale()] ?? reset($subject) ?: null;
                            }
                            $subject = is_string($subject) ? trim($subject) : '';

                            return trim($label.($subject !== '' ? ' - '.$subject : ''));
                        })
                        ->schema([
                            TextInput::make('send_after_minutes')
                                ->label('Versturen na (minuten)')
                                ->helperText('1440 = 1 dag, 10080 = 7 dagen, 20160 = 14 dagen, 43200 = 30 dagen')
                                ->numeric()
                                ->minValue(1)
                                ->default(20160)
                                ->required(),
                            Toggle::make('is_active')
                                ->label('Actief')
                                ->default(true),
                            TextInput::make('subject')
                                ->label('Onderwerp')
                                ->helperText('Beschikbare '.self::VARIABLES_HELP)
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),
                            Builder::make('blocks')
                                ->label('Inhoud blokken')
                                ->helperText(self::VARIABLES_HELP.' - werken in elk tekst-, link- en code-veld hieronder.')
                                ->blocks([
                                    Builder\Block::make('heading')
                                        ->label('Kop')
                                        ->icon('heroicon-o-bars-3-bottom-left')
                                        ->schema([
                                            TextInput::make('content')
                                                ->label('Tekst')
                                                ->helperText(self::VARIABLES_HELP)
                                                ->required(),
                                        ]),
                                    Builder\Block::make('paragraph')
                                        ->label('Tekst')
                                        ->icon('heroicon-o-document-text')
                                        ->schema([
                                            RichEditor::make('content')
                                                ->label('Tekst')
                                                ->helperText(self::VARIABLES_HELP)
                                                ->toolbarButtons([
                                                    'bold', 'italic', 'underline', 'strike',
                                                    'link', 'bulletList', 'orderedList', 'h2', 'h3',
                                                ]),
                                        ]),
                                    Builder\Block::make('button')
                                        ->label('Knop')
                                        ->icon('heroicon-o-cursor-arrow-rays')
                                        ->schema([
                                            TextInput::make('label')
                                                ->label('Knoptekst')
                                                ->helperText(self::VARIABLES_HELP)
                                                ->default('Bekijk')
                                                ->required(),
                                            TextInput::make('url')
                                                ->label('URL')
                                                ->helperText(self::VARIABLES_HELP.' - gebruik :reviewUrl: voor de review-pagina of :siteUrl: voor de homepage.')
                                                ->default(':reviewUrl:')
                                                ->required(),
                                        ]),
                                    Builder\Block::make('image')
                                        ->label('Afbeelding')
                                        ->icon('heroicon-o-photo')
                                        ->schema([
                                            TextInput::make('url')
                                                ->label('URL')
                                                ->helperText(self::VARIABLES_HELP)
                                                ->required(),
                                            TextInput::make('alt')
                                                ->label('Alt-tekst')
                                                ->helperText(self::VARIABLES_HELP),
                                            TextInput::make('link')
                                                ->label('Klik-link (optioneel)')
                                                ->helperText('Maakt de afbeelding klikbaar. '.self::VARIABLES_HELP),
                                        ]),
                                    Builder\Block::make('divider')
                                        ->label('Scheidingslijn')
                                        ->icon('heroicon-o-minus')
                                        ->schema([]),
                                    Builder\Block::make('usp')
                                        ->label('USPs')
                                        ->icon('heroicon-o-check-badge')
                                        ->maxItems(1)
                                        ->schema([
                                            Textarea::make('items')
                                                ->label('USPs (één per regel)')
                                                ->helperText('Voer elke USP op een nieuwe regel in. '.self::VARIABLES_HELP)
                                                ->rows(4)
                                                ->default("Gratis verzending\nSnel geleverd\nVeilig betalen"),
                                        ]),
                                    Builder\Block::make('discount')
                                        ->label('Kortingscode')
                                        ->icon('heroicon-o-tag')
                                        ->maxItems(1)
                                        ->schema([
                                            TextInput::make('label')
                                                ->label('Tekst boven de code')
                                                ->helperText(self::VARIABLES_HELP)
                                                ->default('Gebruik deze code voor extra korting:'),
                                            TextInput::make('code')
                                                ->label('Code')
                                                ->helperText('Vul een specifieke code in. Optionele '.self::VARIABLES_HELP),
                                        ]),
                                ])
                                ->columnSpanFull()
                                ->collapsible()
                                ->reorderableWithButtons(),
                        ])
                        ->columns(2)
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Naam')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('trigger_status')
                    ->label('Trigger status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Orders::getFulfillmentStatusses()[$state] ?? $state),
                TextColumn::make('steps_count')
                    ->label('Stappen')
                    ->counts('steps')
                    ->badge()
                    ->color('info'),
                IconColumn::make('is_active')
                    ->label('Actief')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('gray'),
                IconColumn::make('cancel_on_link_click')
                    ->label('Cancel bij klik')
                    ->boolean(),
                TextColumn::make('skip_if_recently_ordered_within_days')
                    ->label('Skip cooldown (dagen)')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('updated_at')
                    ->label('Bijgewerkt')
                    ->dateTime('d-m-Y H:i', 'Europe/Amsterdam')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('activate')
                    ->label('Activeren')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => ! $record->is_active)
                    ->action(function ($record) {
                        $record->activate();
                        Notification::make()->title('Flow geactiveerd')->success()->send();
                    }),
                EditAction::make()->button(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrderHandledFlows::route('/'),
            'create' => CreateOrderHandledFlow::route('/create'),
            'edit' => EditOrderHandledFlow::route('/{record}/edit'),
        ];
    }

    protected static function formatDelayLabel(int $minutes): string
    {
        if ($minutes <= 0) {
            return 'Direct';
        }
        if ($minutes % 1440 === 0) {
            $days = (int) ($minutes / 1440);

            return $days.' '.($days === 1 ? 'dag' : 'dagen');
        }
        if ($minutes % 60 === 0) {
            $hours = (int) ($minutes / 60);

            return $hours.' uur';
        }

        return $minutes.' minuten';
    }
}
