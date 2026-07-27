<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use Closure;
use UnitEnum;
use BackedEnum;
use Carbon\Carbon;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Infolists\Components\TextEntry;
use Dashed\DashedMobileApi\MobileApiRegistry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Infolists\Components\KeyValueEntry;
use Dashed\DashedEcommerceCore\Models\AutomationRule;
use Dashed\DashedEcommerceCore\Support\Automation\RuleDryRun;
use Dashed\DashedEcommerceCore\Support\Automation\TimeAnchors;
use Dashed\DashedCore\Classes\Actions\ActionGroups\ToolbarActions;
use Dashed\DashedEcommerceCore\Filament\Resources\AutomationRuleResource\Pages\EditAutomationRule;
use Dashed\DashedEcommerceCore\Filament\Resources\AutomationRuleResource\Pages\ListAutomationRules;
use Dashed\DashedEcommerceCore\Filament\Resources\AutomationRuleResource\Pages\CreateAutomationRule;
use Dashed\DashedEcommerceCore\Filament\Resources\AutomationRuleResource\RelationManagers\RunsRelationManager;

/**
 * CMS-scherm om automatiseringsregels ("als dit gebeurt en deze voorwaarden
 * gelden, doe dat") te bouwen. De mobiele app leest deze regels alleen uit en
 * mag uitsluitend de aan/uit-schakelaar bedienen (zie AutomationRuleController
 * in dashed-mobile-api-integratie); het opstellen van trigger, voorwaarden en
 * acties gebeurt hier, met de volledige context en validatie.
 *
 * Triggers en acties komen uit de mobile-api-registry (MobileApiRegistry),
 * net als MobileOrderActions/OrderAutomationTriggers dat al deden — met
 * dezelfde class_exists-guard, zodat dit scherm niet crasht wanneer
 * dashed-mobile-api (nog) niet geïnstalleerd is.
 */
class AutomationRuleResource extends Resource
{
    /**
     * Mens-leesbare labels voor ConditionEvaluator's operators. Vaste lijst,
     * geen registry — de operators zijn intern engine-gedrag, geen per-trigger
     * configuratie.
     *
     * @var array<string, string>
     */
    private const OPERATOR_LABELS = [
        'eq' => 'is gelijk aan',
        'neq' => 'is niet gelijk aan',
        'gt' => 'is groter dan',
        'lt' => 'is kleiner dan',
        'in' => 'is één van',
        'is_true' => 'is waar',
        'is_false' => 'is niet waar',
    ];

    /**
     * Labels voor het schedule-subformulier van tijd-triggers (type => 'time').
     * De sleutels zijn de enige toegestane waarden — TimeRuleScanner en
     * TimeAnchors lezen exact deze strings uit `schedule`, dus alles wat hier
     * niet in staat wordt bij het opslaan geweigerd (zie scheduleFromFormData).
     *
     * @var array<string, string>
     */
    private const SCHEDULE_ANCHOR_LABELS = [
        'created_at' => 'Aanmaakmoment van de bestelling',
        'paid' => 'Betaalmoment van de bestelling',
    ];

    /** @var array<string, string> */
    private const SCHEDULE_UNIT_LABELS = [
        'hours' => 'Uren',
        'days' => 'Dagen',
    ];

    /** @var array<string, string> */
    private const SCHEDULE_FREQUENCY_LABELS = [
        'daily' => 'Dagelijks',
        'weekly' => 'Wekelijks',
    ];

    /**
     * ISO-weekdagen (1 = maandag .. 7 = zondag), zelfde telling als
     * `Carbon::dayOfWeekIso` dat TimeRuleScanner::recurringDue() gebruikt.
     *
     * @var array<int, string>
     */
    private const SCHEDULE_WEEKDAY_LABELS = [
        1 => 'Maandag',
        2 => 'Dinsdag',
        3 => 'Woensdag',
        4 => 'Donderdag',
        5 => 'Vrijdag',
        6 => 'Zaterdag',
        7 => 'Zondag',
    ];

    /**
     * Formaat voor het `at`-tijdstip van een terugkerende schedule: exact
     * "uu:mm" (24-uurs), zoals TimeRuleScanner::recurringDue() het parseert
     * (`explode(':', ...)`).
     */
    private const SCHEDULE_TIME_REGEX = '/^([01]\d|2[0-3]):[0-5]\d$/';

    protected static ?string $model = AutomationRule::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bolt';
    protected static string|UnitEnum|null $navigationGroup = 'E-commerce';
    protected static ?string $navigationLabel = 'Automatiseringsregels';
    protected static ?string $label = 'Automatiseringsregel';
    protected static ?string $pluralLabel = 'Automatiseringsregels';
    protected static ?int $navigationSort = 57;

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'name',
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        // withMax i.p.v. een relatie eager-loaden: precies genoeg voor de
        // "laatste run"-kolom, zonder N+1 over de hele lijst (zelfde aanpak
        // als AutomationRuleController::index() voor de app).
        return parent::getEloquentQuery()->withMax('runs', 'created_at');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Globale informatie')->columnSpanFull()
                ->schema([
                    Select::make('site_id')
                        ->label('Actief op site')
                        ->options(collect(Sites::getSites())->pluck('name', 'id')->toArray())
                        ->hidden(! (Sites::getAmountOfSites() > 1))
                        ->required(),
                ])
                ->hidden(! (Sites::getAmountOfSites() > 1))
                ->collapsed(fn ($livewire) => $livewire instanceof EditAutomationRule),

            Section::make('Algemeen')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Naam')
                        ->required()
                        ->maxLength(255),
                    Toggle::make('is_active')
                        ->label('Actief')
                        ->default(true)
                        ->helperText('Zet uit om de regel tijdelijk te pauzeren zonder de configuratie te verliezen.'),
                ]),

            Section::make('Trigger')
                ->description('Wanneer moet deze regel proberen te draaien?')
                ->columnSpanFull()
                ->schema([
                    Select::make('trigger')
                        ->label('Trigger')
                        ->options(fn (): array => static::triggerOptions())
                        ->required()
                        ->native(false)
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (Set $set): void {
                            // Oude voorwaarden verwijzen naar velden van de vorige
                            // trigger — die kunnen na een wissel niet meer kloppen.
                            $set('conditions', []);
                            // Idem voor de schedule: relative/recurring-velden van
                            // een vorige tijd-trigger (of een niet-tijd-trigger)
                            // horen niet blijven hangen na een wissel.
                            $set('schedule_anchor', null);
                            $set('schedule_amount', null);
                            $set('schedule_unit', null);
                            $set('schedule_frequency', null);
                            $set('schedule_at', null);
                            $set('schedule_weekday', null);
                        }),
                ]),

            Section::make('Planning')
                ->description('Tijd-triggers draaien niet op een gebeurtenis maar op een periodieke scan (zie RunTimeBasedAutomationRules). Stel hier in wanneer deze regel in aanmerking komt.')
                ->columnSpanFull()
                ->columns(2)
                ->visible(fn (Get $get): bool => static::isTimeTrigger($get('trigger')))
                ->schema([
                    // Relatief: "N uur/dagen na [anker]".
                    Select::make('schedule_anchor')
                        ->label('Anker')
                        ->options(self::SCHEDULE_ANCHOR_LABELS)
                        ->native(false)
                        ->rules(['in:' . implode(',', TimeAnchors::KEYS)])
                        ->visible(fn (Get $get): bool => static::triggerScheduleMode($get('trigger')) === 'relative')
                        ->required(fn (Get $get): bool => static::triggerScheduleMode($get('trigger')) === 'relative'),
                    TextInput::make('schedule_amount')
                        ->label('Aantal')
                        ->integer()
                        ->minValue(1)
                        ->visible(fn (Get $get): bool => static::triggerScheduleMode($get('trigger')) === 'relative')
                        ->required(fn (Get $get): bool => static::triggerScheduleMode($get('trigger')) === 'relative'),
                    Select::make('schedule_unit')
                        ->label('Eenheid')
                        ->options(self::SCHEDULE_UNIT_LABELS)
                        ->native(false)
                        ->rules(['in:' . implode(',', array_keys(self::SCHEDULE_UNIT_LABELS))])
                        ->visible(fn (Get $get): bool => static::triggerScheduleMode($get('trigger')) === 'relative')
                        ->required(fn (Get $get): bool => static::triggerScheduleMode($get('trigger')) === 'relative'),

                    // Terugkerend: dagelijks/wekelijks op een vast tijdstip.
                    Select::make('schedule_frequency')
                        ->label('Frequentie')
                        ->options(self::SCHEDULE_FREQUENCY_LABELS)
                        ->native(false)
                        ->live()
                        ->rules(['in:' . implode(',', array_keys(self::SCHEDULE_FREQUENCY_LABELS))])
                        ->visible(fn (Get $get): bool => static::triggerScheduleMode($get('trigger')) === 'recurring')
                        ->required(fn (Get $get): bool => static::triggerScheduleMode($get('trigger')) === 'recurring')
                        ->afterStateUpdated(function (Set $set, $state): void {
                            // De weekdag is enkel relevant bij 'weekly' — laat 'm
                            // niet stiekem blijven hangen na terugschakelen naar
                            // 'daily'.
                            if ($state !== 'weekly') {
                                $set('schedule_weekday', null);
                            }
                        }),
                    TextInput::make('schedule_at')
                        ->label('Tijdstip')
                        ->placeholder('14:30')
                        ->helperText('Formaat uu:mm (24-uurs), bijvoorbeeld 14:30.')
                        ->rules(['regex:' . self::SCHEDULE_TIME_REGEX])
                        ->visible(fn (Get $get): bool => static::triggerScheduleMode($get('trigger')) === 'recurring')
                        ->required(fn (Get $get): bool => static::triggerScheduleMode($get('trigger')) === 'recurring'),
                    Select::make('schedule_weekday')
                        ->label('Weekdag')
                        ->options(self::SCHEDULE_WEEKDAY_LABELS)
                        ->native(false)
                        ->rules(['in:' . implode(',', array_keys(self::SCHEDULE_WEEKDAY_LABELS))])
                        ->visible(fn (Get $get): bool => static::triggerScheduleMode($get('trigger')) === 'recurring'
                            && $get('schedule_frequency') === 'weekly')
                        ->required(fn (Get $get): bool => static::triggerScheduleMode($get('trigger')) === 'recurring'
                            && $get('schedule_frequency') === 'weekly'),
                ]),

            Section::make('Voorwaarden')
                ->description('Alle voorwaarden moeten kloppen (EN-logica). Leeg laten = de regel geldt voor elke gebeurtenis van deze trigger.')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('conditions')
                        ->label('')
                        ->addActionLabel('Voorwaarde toevoegen')
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->defaultItems(0)
                        ->itemLabel(fn (array $state): ?string => static::conditionItemLabel($state))
                        ->schema([
                            Select::make('field')
                                ->label('Veld')
                                ->options(fn (Get $get): array => static::conditionFieldOptions($get('../../trigger')))
                                ->required()
                                ->native(false)
                                ->live()
                                ->afterStateUpdated(function (Set $set): void {
                                    $set('operator', null);
                                    $set('value', null);
                                }),
                            Select::make('operator')
                                ->label('Operator')
                                ->options(fn (Get $get): array => static::operatorsFor(
                                    static::conditionFieldType($get('../../trigger'), $get('field'))
                                ))
                                ->required()
                                ->native(false)
                                ->live(),
                            // Drie varianten van 'value', elkaar wederzijds uitsluitend
                            // op basis van het veldtype van de gekozen conditie — Filament
                            // heeft geen "polymorf veldtype"-component, dus dit is de
                            // standaard-manier om per situatie een ander input-type te
                            // tonen onder dezelfde state-key.
                            Select::make('value')
                                ->label('Waarde')
                                ->options(fn (Get $get): array => static::conditionValueOptions($get('../../trigger'), $get('field')))
                                ->multiple(fn (Get $get): bool => $get('operator') === 'in')
                                ->native(false)
                                ->searchable()
                                ->visible(fn (Get $get): bool => static::conditionFieldType($get('../../trigger'), $get('field')) === 'select')
                                ->dehydrated(fn (Get $get): bool => static::conditionFieldType($get('../../trigger'), $get('field')) === 'select')
                                ->required(fn (Get $get): bool => static::conditionFieldType($get('../../trigger'), $get('field')) === 'select'),
                            TextInput::make('value')
                                ->label('Waarde')
                                ->numeric()
                                ->visible(fn (Get $get): bool => static::conditionFieldType($get('../../trigger'), $get('field')) === 'number')
                                ->dehydrated(fn (Get $get): bool => static::conditionFieldType($get('../../trigger'), $get('field')) === 'number')
                                ->required(fn (Get $get): bool => static::conditionFieldType($get('../../trigger'), $get('field')) === 'number'),
                            TextInput::make('value')
                                ->label('Waarde')
                                ->visible(fn (Get $get): bool => ! in_array(
                                    static::conditionFieldType($get('../../trigger'), $get('field')),
                                    ['select', 'number', 'boolean'],
                                    true
                                ))
                                ->dehydrated(fn (Get $get): bool => ! in_array(
                                    static::conditionFieldType($get('../../trigger'), $get('field')),
                                    ['select', 'number', 'boolean'],
                                    true
                                )),
                        ])
                        ->columns(3)
                        ->columnSpanFull(),
                ]),

            Section::make('Acties')
                ->description('Deze acties draaien in deze volgorde. Alleen automatiseerbare acties zijn hier te kiezen — sommige acties (bv. een bestelling annuleren) blijven bewust alleen handmatig beschikbaar.')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('actions')
                        ->label('')
                        ->addActionLabel('Actie toevoegen')
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->defaultItems(0)
                        ->itemLabel(fn (array $state): ?string => static::actionItemLabel($state))
                        ->schema([
                            Select::make('key')
                                ->label('Actie')
                                ->options(fn (): array => static::automatableActionOptions())
                                ->required()
                                ->native(false)
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(function (Set $set): void {
                                    $set('params', []);
                                }),
                            Group::make()
                                ->statePath('params')
                                ->columns(2)
                                ->visible(fn (Get $get): bool => static::actionParamFields($get('key')) !== [])
                                ->schema(fn (Get $get): array => static::actionParamFields($get('key'))),
                        ])
                        ->columnSpanFull(),
                ]),
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
                TextColumn::make('trigger')
                    ->label('Trigger')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (string $state): string => static::triggerOptions()[$state] ?? $state),
                TextColumn::make('actions')
                    ->label('Acties')
                    ->state(fn (AutomationRule $record): int => count($record->actions ?? []))
                    ->badge()
                    ->color('gray'),
                IconColumn::make('is_active')
                    ->label('Actief')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('gray'),
                TextColumn::make('site_id')
                    ->label('Site')
                    ->hidden(! (Sites::getAmountOfSites() > 1))
                    ->searchable(),
                TextColumn::make('runs_max_created_at')
                    ->label('Laatste run')
                    ->dateTime('d-m-Y H:i', 'Europe/Amsterdam')
                    ->sortable()
                    ->placeholder('Nog niet gedraaid'),
            ])
            ->filters([
                SelectFilter::make('trigger')
                    ->label('Trigger')
                    ->options(fn (): array => static::triggerOptions()),
            ])
            ->defaultSort('name')
            ->recordActions([
                static::dryRunAction(),
                EditAction::make()
                    ->button(),
                DeleteAction::make(),
            ])
            ->toolbarActions(ToolbarActions::getActions())
            ->emptyStateHeading('Nog geen automatiseringsregels')
            ->emptyStateDescription('Maak een regel aan om acties automatisch te laten draaien zodra een trigger matcht.')
            ->emptyStateIcon('heroicon-o-bolt');
    }

    public static function getRelations(): array
    {
        return [
            RunsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAutomationRules::route('/'),
            'create' => CreateAutomationRule::route('/create'),
            'edit' => EditAutomationRule::route('/{record}/edit'),
        ];
    }

    /**
     * Droogloop ("test tegen bestelling"): een beheerder kiest een bestelling
     * (op factuurnummer of ID) en ziet of déze regel zou matchen en welke
     * acties zouden draaien — zonder dat er iets wordt uitgevoerd. Delegeert
     * de match/actie-beschrijving volledig naar RuleDryRun (die zelf
     * ConditionEvaluator/AutomationContext hergebruikt); deze actie roept dus
     * zelf nooit een actie-`handle` aan.
     *
     * Voor een tijd-regel (schedule niet-null, zie isScheduleRule()) bestaat
     * er geen "kies een bestelling" — er is geen enkel event/order waartegen
     * getest wordt, alleen een cadans die op elk moment nul of meer orders
     * kan raken. Die variant toont daarom direct "zou nu vuren voor N
     * bestellingen" (RuleDryRun::forSchedule()) in plaats van de order-kiezer.
     *
     * Zelfde "view-only modal"-patroon als OrderResource::table()'s
     * bekijk-actie: geen submit-knop, enkel een sluitknop — dit scherm
     * registreert of wijzigt niets.
     */
    private static function dryRunAction(): Action
    {
        return Action::make('dryRun')
            ->label('Testen')
            ->icon('heroicon-o-beaker')
            ->color('gray')
            ->modalHeading(fn (AutomationRule $record): string => "Droogloop — {$record->name}")
            ->modalDescription(fn (AutomationRule $record): string => static::isScheduleRule($record)
                ? 'Toont hoeveel bestellingen deze regel nú zou raken en welke acties zouden draaien. Er wordt niets uitgevoerd.'
                : 'Kies een bestelling om te zien of deze regel zou matchen en welke acties zouden draaien. Er wordt niets uitgevoerd.')
            ->modalWidth('2xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Sluiten')
            ->schema(fn (AutomationRule $record): array => static::isScheduleRule($record)
                ? static::dryRunScheduleSchema($record)
                : static::dryRunSchema($record));
    }

    /**
     * Een tijd-regel heeft een `schedule` (gevuld door
     * scheduleFromFormData(), zie onderaan) — een event-regel niet. Dat
     * onderscheid, niet `trigger`-string-matching, bepaalt hier welke
     * droogloop-variant getoond wordt: het is exact hetzelfde veld dat
     * RunTimeBasedAutomationRules gebruikt om tijd-regels te selecteren
     * (`whereNotNull('schedule')`).
     */
    private static function isScheduleRule(AutomationRule $record): bool
    {
        return $record->schedule !== null;
    }

    /**
     * Droogloop-resultaat voor een tijd-regel: "zou nu vuren voor N
     * bestellingen" plus de acties die zouden draaien, via
     * RuleDryRun::forSchedule() — die zelf nooit een actie-`handle` aanroept
     * en nooit `AutomationEngine::run()`. `Carbon::now()` (niet een door de
     * beheerder gekozen moment): de droogloop beantwoordt "wat zou er NU
     * gebeuren", dezelfde vraag als de uurlijkse scan-job zichzelf stelt.
     *
     * @return array<int, TextEntry>
     */
    private static function dryRunScheduleSchema(AutomationRule $record): array
    {
        $result = RuleDryRun::forSchedule($record, Carbon::now());

        return [
            TextEntry::make('dry_run_would_fire_count')
                ->label('Zou nu vuren voor')
                ->state("{$result['would_fire_count']} bestelling(en)")
                ->badge()
                ->color($result['would_fire_count'] > 0 ? 'success' : 'gray'),

            TextEntry::make('dry_run_schedule_actions')
                ->label('Acties die zouden draaien')
                ->state(static::dryRunScheduleActionsForDisplay($result['actions'], $result['would_fire_count']))
                ->listWithLineBreaks()
                ->bulleted()
                ->columnSpanFull(),
        ];
    }

    /**
     * @param  array<int, array{key: string, label: string, params: array<string, mixed>}>  $actions
     * @return array<int, string>
     */
    private static function dryRunScheduleActionsForDisplay(array $actions, int $wouldFireCount): array
    {
        if ($actions === []) {
            return $wouldFireCount > 0
                ? ['Deze regel heeft geen acties.']
                : ['Niet van toepassing — er zijn nu geen bestellingen die deze regel zouden raken.'];
        }

        return collect($actions)
            ->map(fn (array $action): string => $action['params'] === []
                ? $action['label']
                : "{$action['label']} (" . json_encode($action['params']) . ')')
            ->all();
    }

    /** @return array<int, Field|Section> */
    private static function dryRunSchema(AutomationRule $record): array
    {
        return [
            Select::make('order_id')
                ->label('Bestelling (factuurnummer of ID)')
                ->placeholder('Zoek op factuurnummer of ID')
                ->native(false)
                ->searchable()
                ->live()
                ->getSearchResultsUsing(fn (string $search): array => static::dryRunOrderOptions($record, $search))
                ->getOptionLabelUsing(fn ($value): ?string => static::dryRunOrderLabel(static::dryRunFindOrder($record, $value))),

            Section::make('Resultaat')
                ->columnSpanFull()
                ->visible(fn (Get $get): bool => filled($get('order_id')))
                ->schema(fn (Get $get): array => static::dryRunResultSchema($record, $get('order_id'))),
        ];
    }

    /**
     * Bestellingen van de site van déze regel, gezocht op factuurnummer
     * (deelstring) of exact ID — zelfde site-scope als elders in dit scherm
     * (bv. conditionValueOptions): een regel van site A mag nooit tegen een
     * order van site B getest worden.
     *
     * @return array<int|string, string|null>
     */
    private static function dryRunOrderOptions(AutomationRule $record, string $search): array
    {
        return Order::query()
            ->where('site_id', $record->site_id)
            ->where(function (Builder $query) use ($search): void {
                $query->where('invoice_id', 'like', "%{$search}%");
                if (is_numeric($search)) {
                    $query->orWhere('id', (int) $search);
                }
            })
            ->orderByDesc('id')
            ->limit(25)
            ->get()
            ->mapWithKeys(fn (Order $order): array => [$order->id => static::dryRunOrderLabel($order)])
            ->all();
    }

    /**
     * Haalt de order voor de droogloop site-gescoped op — dezelfde scope als
     * dryRunOrderOptions() hierboven. Zonder deze check zou een beheerder via
     * Livewire-state een order-ID van een ándere site kunnen injecteren
     * (bv. via de dev tools) en de match-berekening (dryRunResultSchema) én
     * het optie-label (getOptionLabelUsing) daartegen laten draaien, ook al
     * biedt de zoek-Select zelf alleen orders van de eigen site aan. Een
     * order die niet bij de site van déze regel hoort is hier "niet
     * gevonden", nooit een geldige order om tegen te matchen.
     */
    private static function dryRunFindOrder(AutomationRule $record, mixed $orderId): ?Order
    {
        if (! is_numeric($orderId)) {
            return null;
        }

        return Order::query()
            ->where('site_id', $record->site_id)
            ->find($orderId);
    }

    /**
     * 'PROFORMA'/'RETURN' zijn placeholder-waarden in invoice_id voor orders
     * zonder echte factuur — daarvoor toont dit het order-ID in plaats van
     * die placeholder-tekst.
     */
    private static function dryRunOrderLabel(?Order $order): ?string
    {
        if ($order === null) {
            return null;
        }

        $invoice = filled($order->invoice_id) && ! in_array($order->invoice_id, ['PROFORMA', 'RETURN'], true)
            ? $order->invoice_id
            : "#{$order->id}";

        return "{$invoice} — {$order->name}";
    }

    /**
     * Het resultaat-blok voor de gekozen order: match ja/nee (of "onbekend",
     * zie dryRunMatchDisplay()), de gebruikte contextwaarden en de acties die
     * zouden draaien — exact de vorm die RuleDryRun::for() teruggeeft, hier
     * weergegeven met Filament's eigen infolist-componenten (geen custom
     * HTML, geen hardcoded kleuren). De order wordt via dryRunFindOrder()
     * site-gescoped opgehaald: een order van een andere site is hier "niet
     * gevonden", niet een geldig testdoel.
     *
     * @return array<int, TextEntry|KeyValueEntry>
     */
    private static function dryRunResultSchema(AutomationRule $record, mixed $orderId): array
    {
        $order = static::dryRunFindOrder($record, $orderId);

        if ($order === null) {
            return [
                TextEntry::make('dry_run_missing')
                    ->label('')
                    ->state('Deze bestelling kon niet worden gevonden.'),
            ];
        }

        $result = RuleDryRun::for($record, $order);
        $matchDisplay = static::dryRunMatchDisplay($result);

        return [
            TextEntry::make('dry_run_matched')
                ->label('Match')
                ->state($matchDisplay['label'])
                ->badge()
                ->color($matchDisplay['color']),

            KeyValueEntry::make('dry_run_context')
                ->label('Gebruikte contextwaarden')
                ->keyLabel('Veld')
                ->valueLabel('Waarde')
                ->state(static::dryRunContextForDisplay($result['context'])),

            TextEntry::make('dry_run_actions')
                ->label('Acties die zouden draaien')
                ->state(static::dryRunActionsForDisplay($result['actions'], $result['matched'], $result['undeterminable_fields']))
                ->listWithLineBreaks()
                ->bulleted()
                ->columnSpanFull(),
        ];
    }

    /**
     * Vertaalt RuleDryRun's ruwe resultaat naar een label + semantische kleur
     * voor de "Match"-regel. Wanneer `undeterminable_fields` niet leeg is, mag
     * de ruwe `matched`-boolean (die door ConditionEvaluator's fail-safe
     * altijd `false` teruggeeft zodra een conditieveld ontbreekt) NOOIT als
     * "Nee, deze regel zou niet draaien" getoond worden — dat zou een gok
     * voordoen als een feit. In dat geval toont dit i.p.v. een definitief
     * ja/nee een waarschuwing (kleur 'warning', geen 'danger': het is geen
     * fout, enkel onvolledige informatie) met de betrokken veldnamen.
     *
     * @param  array{matched: bool, undeterminable_fields: array<int, string>, context: array<string, mixed>, actions: array<int, array<string, mixed>>}  $result
     * @return array{label: string, color: string}
     */
    private static function dryRunMatchDisplay(array $result): array
    {
        if ($result['undeterminable_fields'] !== []) {
            $fields = collect($result['undeterminable_fields'])
                ->map(fn (string $field): string => static::conditionFieldLabel($field))
                ->implode(', ');

            return [
                'label' => "Onbekend — deze regel filtert ook op '{$fields}'. Die waarde bestaat pas tijdens de echte gebeurtenis en is in een droogloop niet na te bootsen, dus is de match hierboven niet doorslaggevend.",
                'color' => 'warning',
            ];
        }

        return [
            'label' => $result['matched'] ? 'Ja, deze regel zou draaien' : 'Nee, deze regel zou niet draaien',
            'color' => $result['matched'] ? 'success' : 'gray',
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    private static function dryRunContextForDisplay(array $context): array
    {
        $display = [];
        foreach ($context as $field => $value) {
            $display[static::conditionFieldLabel($field)] = match (true) {
                is_bool($value) => $value ? 'waar' : 'onwaar',
                is_array($value) => implode(', ', $value),
                $value === null => '-',
                default => (string) $value,
            };
        }

        return $display;
    }

    /**
     * @param  array<int, array{key: string, label: string, params: array<string, mixed>}>  $actions
     * @param  array<int, string>  $undeterminableFields  zie RuleDryRun::for(): niet-leeg betekent
     *         dat 'matched' geen betrouwbaar definitief antwoord is, dus tonen
     *         we hier ook geen "matcht niet"-boodschap alsof dat vaststaat.
     * @return array<int, string>
     */
    private static function dryRunActionsForDisplay(array $actions, bool $matched, array $undeterminableFields = []): array
    {
        if ($actions === []) {
            return ($matched || $undeterminableFields !== [])
                ? ['Deze regel heeft geen acties.']
                : ['Niet van toepassing — de regel matcht niet.'];
        }

        return collect($actions)
            ->map(fn (array $action): string => $action['params'] === []
                ? $action['label']
                : "{$action['label']} (" . json_encode($action['params']) . ')')
            ->all();
    }

    /**
     * De MobileApiRegistry-singleton, of null wanneer dashed-mobile-api (nog)
     * niet geïnstalleerd is. Zelfde class_exists-guard als
     * MobileOrderActions/OrderAutomationTriggers — dit scherm mag nooit fataal
     * zijn omdat een ander package ontbreekt, het toont dan gewoon lege
     * keuzelijsten.
     */
    private static function registry(): ?MobileApiRegistry
    {
        if (! class_exists(MobileApiRegistry::class)) {
            return null;
        }

        return app(MobileApiRegistry::class);
    }

    /** @return array<string, string> */
    private static function triggerOptions(): array
    {
        $registry = static::registry();

        if ($registry === null) {
            return [];
        }

        $options = [];
        foreach ($registry->automationTriggers() as $key => $trigger) {
            // Tijd-triggers (type === 'time') waren hier tijdelijk uitgesloten
            // (Task 2) totdat het schedule-subformulier bestond. Dat formulier
            // is er nu (zie de 'Planning'-Section in form()), dus time.relative/
            // time.recurring zijn weer gewoon te kiezen.
            $options[$key] = (string) ($trigger['label'] ?? $key);
        }

        return $options;
    }

    /**
     * `type`/`mode` van de gekozen trigger, gelezen via de registry-helper
     * (zie klasse-doc): `type === 'time'` bepaalt of de 'Planning'-Section
     * zichtbaar is, `mode` (relative/recurring) welke velden daarin.
     * Een onbekende of niet-tijd-trigger geeft `[null, null]` — de
     * Planning-Section is dan simpelweg onzichtbaar.
     *
     * @return array{type: ?string, mode: ?string}
     */
    private static function triggerMeta(?string $triggerKey): array
    {
        if (! is_string($triggerKey) || $triggerKey === '') {
            return ['type' => null, 'mode' => null];
        }

        $registry = static::registry();
        $trigger = $registry?->automationTrigger($triggerKey);

        return [
            'type' => is_array($trigger) ? ($trigger['type'] ?? null) : null,
            'mode' => is_array($trigger) ? ($trigger['mode'] ?? null) : null,
        ];
    }

    private static function isTimeTrigger(?string $triggerKey): bool
    {
        return static::triggerMeta($triggerKey)['type'] === 'time';
    }

    private static function triggerScheduleMode(?string $triggerKey): ?string
    {
        return static::triggerMeta($triggerKey)['mode'];
    }

    /**
     * Bouwt de `schedule`-array voor opslag uit de losse `schedule_*`-
     * formuliervelden, en whitelist daarbij elke waarde — dit is de enige
     * plek waar `AutomationRule::schedule` gevuld wordt. KRITIEK: dit
     * package draait met een globale `Model::unguard()`, dus zonder deze
     * whitelist zou een onbekende/ontbrekende waarde (of de rauwe
     * `schedule_*`-sleutels zelf, die geen kolommen zijn) linea recta naar
     * de database kunnen lekken. Een trigger zonder tijd-mode (of een
     * ongeldige combinatie) levert `null` op — precies wat een event-regel
     * al had (zie AutomationRuleScheduleColumnTest).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private static function scheduleFromFormData(array $data): ?array
    {
        $trigger = $data['trigger'] ?? null;
        $mode = static::triggerScheduleMode(is_string($trigger) ? $trigger : null);

        return match ($mode) {
            'relative' => static::relativeScheduleFromFormData($data),
            'recurring' => static::recurringScheduleFromFormData($data),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{mode: string, anchor: string, amount: int, unit: string}|null
     */
    private static function relativeScheduleFromFormData(array $data): ?array
    {
        $anchor = $data['schedule_anchor'] ?? null;
        $amount = $data['schedule_amount'] ?? null;
        $unit = $data['schedule_unit'] ?? null;

        if (! is_string($anchor) || ! in_array($anchor, TimeAnchors::KEYS, true)) {
            return null;
        }
        if (! is_numeric($amount) || (int) $amount < 1) {
            return null;
        }
        if (! is_string($unit) || ! array_key_exists($unit, self::SCHEDULE_UNIT_LABELS)) {
            return null;
        }

        return [
            'mode' => 'relative',
            'anchor' => $anchor,
            'amount' => (int) $amount,
            'unit' => $unit,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{mode: string, frequency: string, at: string, weekday?: int}|null
     */
    private static function recurringScheduleFromFormData(array $data): ?array
    {
        $frequency = $data['schedule_frequency'] ?? null;
        $at = $data['schedule_at'] ?? null;
        $weekday = $data['schedule_weekday'] ?? null;

        if (! is_string($frequency) || ! array_key_exists($frequency, self::SCHEDULE_FREQUENCY_LABELS)) {
            return null;
        }
        if (! is_string($at) || ! preg_match(self::SCHEDULE_TIME_REGEX, $at)) {
            return null;
        }

        $schedule = ['mode' => 'recurring', 'frequency' => $frequency, 'at' => $at];

        if ($frequency === 'weekly') {
            if (! is_numeric($weekday) || ! array_key_exists((int) $weekday, self::SCHEDULE_WEEKDAY_LABELS)) {
                return null;
            }
            $schedule['weekday'] = (int) $weekday;
        }

        return $schedule;
    }

    /**
     * Vervangt de losse `schedule_*`-formuliervelden in $data door de
     * gebundelde `schedule`-array. Aangeroepen vanuit
     * CreateAutomationRule::mutateFormDataBeforeCreate() en
     * EditAutomationRule::mutateFormDataBeforeSave() — de `schedule_*`-
     * sleutels moeten hier ALTIJD verwijderd worden, ook als scheduleFrom
     * FormData() null teruggeeft: het zijn geen kolommen op AutomationRule,
     * en door de globale `Model::unguard()` zou Eloquent anders een query
     * tegen niet-bestaande kolommen proberen te bouwen.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function withScheduleData(array $data): array
    {
        $data['schedule'] = static::scheduleFromFormData($data);

        unset(
            $data['schedule_anchor'],
            $data['schedule_amount'],
            $data['schedule_unit'],
            $data['schedule_frequency'],
            $data['schedule_at'],
            $data['schedule_weekday'],
        );

        return $data;
    }

    /**
     * Het omgekeerde van withScheduleData(): zet een bestaande `schedule`-
     * array uit de database uit naar de losse `schedule_*`-formuliervelden,
     * zodat het bewerken van een bestaande tijd-regel het formulier voorvult.
     * Aangeroepen vanuit EditAutomationRule::mutateFormDataBeforeFill().
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function scheduleFormDataFromRecord(array $data): array
    {
        $schedule = $data['schedule'] ?? null;
        if (! is_array($schedule)) {
            return $data;
        }

        $data['schedule_anchor'] = $schedule['anchor'] ?? null;
        $data['schedule_amount'] = $schedule['amount'] ?? null;
        $data['schedule_unit'] = $schedule['unit'] ?? null;
        $data['schedule_frequency'] = $schedule['frequency'] ?? null;
        $data['schedule_at'] = $schedule['at'] ?? null;
        $data['schedule_weekday'] = $schedule['weekday'] ?? null;

        return $data;
    }

    /** @return array<int, array<string, mixed>> */
    private static function conditionFieldsFor(?string $triggerKey): array
    {
        if (! is_string($triggerKey) || $triggerKey === '') {
            return [];
        }

        $registry = static::registry();
        if ($registry === null) {
            return [];
        }

        $trigger = $registry->automationTrigger($triggerKey);
        if ($trigger === null) {
            return [];
        }

        $fields = $trigger['fields'] ?? [];

        return is_array($fields) ? $fields : [];
    }

    /** @return array<string, string> */
    private static function conditionFieldOptions(?string $triggerKey): array
    {
        $options = [];
        foreach (static::conditionFieldsFor($triggerKey) as $field) {
            $name = $field['name'] ?? null;
            if (! is_string($name) || $name === '') {
                continue;
            }
            $options[$name] = (string) ($field['label'] ?? $name);
        }

        return $options;
    }

    /** @return array<string, mixed>|null */
    private static function conditionField(?string $triggerKey, ?string $fieldName): ?array
    {
        if (! is_string($fieldName) || $fieldName === '') {
            return null;
        }

        foreach (static::conditionFieldsFor($triggerKey) as $field) {
            if (($field['name'] ?? null) === $fieldName) {
                return $field;
            }
        }

        return null;
    }

    private static function conditionFieldType(?string $triggerKey, ?string $fieldName): ?string
    {
        $field = static::conditionField($triggerKey, $fieldName);

        return isset($field['type']) ? (string) $field['type'] : null;
    }

    /** @return array<int|string, mixed> */
    private static function conditionValueOptions(?string $triggerKey, ?string $fieldName): array
    {
        $field = static::conditionField($triggerKey, $fieldName);
        if ($field === null) {
            return [];
        }

        return static::resolveOptions($field['options'] ?? []);
    }

    /**
     * Een menselijk label voor een conditie-veld, gezocht over alle
     * geregistreerde triggers heen — de itemLabel-closure van de Repeater
     * heeft geen betrouwbare toegang tot de bovenliggende 'trigger'-waarde, dus
     * i.p.v. daarop te leunen (fragiel), zoeken we de eerste trigger die dit
     * veld kent. In fase 1 delen alle order-triggers dezelfde veldenset, dus
     * dit geeft in de praktijk altijd het juiste label.
     */
    private static function conditionFieldLabel(string $fieldName): string
    {
        $registry = static::registry();
        if ($registry !== null) {
            foreach ($registry->automationTriggers() as $trigger) {
                foreach (($trigger['fields'] ?? []) as $field) {
                    if (($field['name'] ?? null) === $fieldName) {
                        return (string) ($field['label'] ?? $fieldName);
                    }
                }
            }
        }

        return $fieldName;
    }

    private static function conditionItemLabel(array $state): ?string
    {
        $field = $state['field'] ?? null;
        if (! is_string($field) || $field === '') {
            return null;
        }

        $operator = $state['operator'] ?? null;
        $operatorLabel = is_string($operator) ? (self::OPERATOR_LABELS[$operator] ?? $operator) : '…';

        $value = $state['value'] ?? null;
        $valueLabel = match (true) {
            is_array($value) => implode(', ', $value),
            $value === null => '',
            default => (string) $value,
        };

        return trim(static::conditionFieldLabel($field) . ' ' . $operatorLabel . ' ' . $valueLabel);
    }

    /**
     * @param  string|null  $fieldType
     * @return array<string, string>
     */
    private static function operatorsFor(?string $fieldType): array
    {
        $keys = match ($fieldType) {
            'number' => ['eq', 'neq', 'gt', 'lt'],
            'select' => ['eq', 'neq', 'in'],
            'boolean' => ['is_true', 'is_false'],
            default => ['eq', 'neq'],
        };

        $options = [];
        foreach ($keys as $key) {
            $options[$key] = self::OPERATOR_LABELS[$key];
        }

        return $options;
    }

    /**
     * Alleen acties met `automatable === true` mogen in een regel gekozen
     * worden — acties zoals 'cancel' (onomkeerbaar) of 'track_and_trace'
     * (waarde niet vooraf te kennen) zijn bewust uitgesloten en blijven alleen
     * als handmatige Filament-actie op de order beschikbaar.
     *
     * @return array<string, string>
     */
    private static function automatableActionOptions(): array
    {
        $registry = static::registry();
        if ($registry === null) {
            return [];
        }

        $options = [];
        foreach ($registry->orderActions() as $key => $action) {
            if (($action['automatable'] ?? false) === true) {
                $options[$key] = (string) ($action['label'] ?? $key);
            }
        }

        return $options;
    }

    private static function actionItemLabel(array $state): ?string
    {
        $key = $state['key'] ?? null;
        if (! is_string($key) || $key === '') {
            return null;
        }

        $registry = static::registry();
        $action = $registry?->orderAction($key);

        return (string) ($action['label'] ?? $key);
    }

    /**
     * Bouwt de parameter-velden voor de gekozen actie. Geeft niets terug voor
     * een onbekende of niet-automatiseerbare actie (defensief — de Select
     * hierboven laat al alleen automatiseerbare acties kiezen, maar een
     * bestaande regel kan naar een inmiddels niet-meer-automatiseerbare of
     * verwijderde actie wijzen).
     *
     * @return array<int, Field>
     */
    private static function actionParamFields(?string $actionKey): array
    {
        if (! is_string($actionKey) || $actionKey === '') {
            return [];
        }

        $registry = static::registry();
        if ($registry === null) {
            return [];
        }

        $action = $registry->orderAction($actionKey);
        if ($action === null || ($action['automatable'] ?? false) !== true) {
            return [];
        }

        $fields = $action['fields'] ?? [];
        if (! is_array($fields)) {
            return [];
        }

        $components = [];
        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }
            $component = static::actionParamFieldComponent($field);
            if ($component !== null) {
                $components[] = $component;
            }
        }

        return $components;
    }

    /**
     * Eén actie-parameterveld → een Filament form-component. `default` is
     * POLYMORF, net als `options`: een closure (bv. `fn (Order $o) => $o->email`)
     * verwacht een order die er in de regel-bouwer niet is — die default wordt
     * bewust nooit aangeroepen. Zo'n veld is ook nooit verplicht in de regel
     * zelf, ook niet als de actie-definitie 'required' zegt: dat 'required'
     * geldt voor een mens die 'm per order invult, niet voor een regel die
     * juist bedoeld is om per order te resolven (de actie-handler valt zelf al
     * terug op diezelfde orderwaarde, bv. `$data['email'] ?? $o->email`).
     *
     * @param  array<string, mixed>  $field
     */
    private static function actionParamFieldComponent(array $field): ?Field
    {
        $name = $field['name'] ?? null;
        if (! is_string($name) || $name === '') {
            return null;
        }

        $label = (string) ($field['label'] ?? $name);
        $type = (string) ($field['type'] ?? 'text');
        $default = $field['default'] ?? null;
        $isDynamicDefault = $default instanceof Closure;

        $component = match ($type) {
            'email' => TextInput::make($name)->email(),
            'number' => TextInput::make($name)->numeric(),
            'textarea' => Textarea::make($name)->rows(3),
            'checkbox' => Toggle::make($name),
            'select' => Select::make($name)
                ->native(false)
                ->options(static::resolveOptions($field['options'] ?? [])),
            default => TextInput::make($name),
        };

        $component->label($label);

        $isRequired = ($field['required'] ?? false) === true && ! $isDynamicDefault;
        $component->required($isRequired);

        if ($isDynamicDefault) {
            $component->helperText('Leeg laten om de waarde uit de bestelling zelf te gebruiken.');
        } elseif ($default !== null) {
            $component->default($default);
        }

        return $component;
    }

    /**
     * `options` op een trigger- of actieveld is POLYMORF: óf een kant-en-klare
     * array, óf een callable die er één teruggeeft (voor opties die een query
     * nodig hebben, bv. landen/origins/betaalmethodes). Beide vormen moeten
     * hier afgehandeld worden — een callable blind als array behandelen (of
     * andersom) crasht het scherm.
     */
    private static function resolveOptions(mixed $options): array
    {
        if (is_callable($options)) {
            $resolved = $options();

            return is_array($resolved) ? $resolved : [];
        }

        return is_array($options) ? $options : [];
    }
}
