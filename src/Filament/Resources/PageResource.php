<?php

namespace Qubiqx\QcommerceCore\Filament\Resources;

use Closure;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Str;
use Qubiqx\QcommerceCore\Classes\Sites;
use Qubiqx\QcommerceCore\Filament\Resources\PageResource\Pages\CreatePage;
use Qubiqx\QcommerceCore\Filament\Resources\PageResource\Pages\EditPage;
use Qubiqx\QcommerceCore\Filament\Resources\PageResource\Pages\ListPages;
use Qubiqx\QcommerceCore\Models\Page;

class PageResource extends Resource
{
    use Translatable;

    protected static ?string $model = Page::class;
//    public static function getModel(): string
//    {
//        return cms()->model('Page');
//    }

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationGroup = 'Content';
    protected static ?string $navigationLabel = 'Pagina\'s';
    protected static ?string $label = 'Pagina';
    protected static ?string $pluralLabel = 'Pagina\'s';

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'name',
            'slug',
            'content',
            'meta_title',
            'meta_description',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Globale informatie')
                    ->schema([
                        DatePicker::make('start_date')
                            ->label('Vul een startdatum in voor de pagina:')
                            ->helperText('Indien je geen startdatum opgeeft, is de pagina direct zichtbaar')
                            ->rules([
                                'nullable',
                                'date',
                            ]),
                        DatePicker::make('end_date')
                            ->label('Vul een einddatum in voor de pagina:')
                            ->helperText('Indien je geen einddatum opgeeft, vervalt de pagina niet')
                            ->rules([
                                'nullable',
                                'date',
                                'after:startDate',
                            ]),
                        Toggle::make('is_home')
                            ->label('Dit is de homepagina'),
                        Select::make('site_id')
                            ->label('Actief op site')
                            ->options(collect(Sites::getSites())->pluck('name', 'id'))
                            ->hidden(function () {
                                return ! (Sites::getAmountOfSites() > 1);
                            })
                            ->required(),
                    ])
                    ->collapsed(fn ($livewire) => $livewire instanceof EditPage),
                Section::make('Content')
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->rules([
                                'max:255',
                            ])
                            ->reactive()
                            ->afterStateUpdated(function (Closure $set, $state, $livewire) {
                                if ($livewire instanceof CreatePage) {
                                    $set('slug', Str::slug($state));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->unique('qcommerce__pages', 'slug', fn ($record) => $record)
                            ->helperText('Laat leeg om automatisch te laten genereren')
                            ->required()
                            ->rules([
                                'max:255',
                            ]),
                        TextInput::make('meta_title')
                            ->label('Meta title')
                            ->rules([
                                'nullable',
                                'min:20',
                                'max:60',
                            ]),
                        Textarea::make('meta_description')
                            ->label('Meta descriptie')
                            ->rows(2)
                            ->rules([
                                'nullable',
                                'min:30',
                                'max:158',
                            ]),
                        SpatieMediaLibraryFileUpload::make('image')
                            ->collection(fn ($livewire) => "meta-image-{$livewire->activeFormLocale}")
                            ->name('Meta afbeelding')
                            ->image(),
//                        ->disk('qcommerce'),
//                            ->maxSize(10240)

                        Builder::make('content')
                            ->blocks(cms()->builder('blocks')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Naam')
                    ->sortable()
                    ->searchable([
                        'name',
                        'slug',
                        'content',
                        'meta_title',
                        'meta_description',
                    ]),
                TextColumn::make('site_id')
                    ->label('Actief op site')
                    ->sortable()
                    ->hidden(! (Sites::getAmountOfSites() > 1))
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->getStateUsing(fn ($record) => ucfirst($record->status)),
            ])
            ->filters([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
