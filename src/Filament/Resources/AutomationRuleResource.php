<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use Closure;
use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
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
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Tables\Filters\SelectFilter;
use Dashed\DashedMobileApi\MobileApiRegistry;
use Dashed\DashedEcommerceCore\Models\AutomationRule;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
                        }),
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
            $options[$key] = (string) ($trigger['label'] ?? $key);
        }

        return $options;
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
