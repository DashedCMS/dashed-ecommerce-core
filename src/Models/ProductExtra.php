<?php

namespace Dashed\DashedEcommerceCore\Models;

use Spatie\Activitylog\LogOptions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Dashed\DashedCore\Models\Concerns\HasCustomBlocks;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductExtra extends Model
{
    use HasTranslations;
    use SoftDeletes;
    use LogsActivity;
    use HasCustomBlocks;

    protected static $logFillable = true;

    protected $fillable = [
        'product_id',
        'name',
        'type',
        'required',
    ];

    public $translatable = [
        'name',
    ];

    protected $table = 'dashed__product_extras';

    public static function booted()
    {
        parent::booted();

        static::creating(function ($productExtra) {
            if ($productExtra->global) {
                $productExtra->order = ProductExtra::where('global', 1)->max('order') + 1;
            }
        });

        static::deleting(function ($productExtra) {
            foreach ($productExtra->productExtraOptions as $productExtraOption) {
                $productExtraOption->delete();
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'dashed__product_extra_product', 'product_extra_id', 'product_id');
    }

    public function productCategories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'dashed__product_extra_product_category', 'product_extra_id', 'product_category_id');
    }

    public function productExtraOptions(): HasMany
    {
        return $this->hasMany(ProductExtraOption::class)
            ->orderBy('order');
    }

    public static function getFilamentFields(): array
    {
        return [
            TextInput::make('name')
                ->label('Naam')
                ->required()
                ->maxLength(255),
            Select::make('type')
                ->label('Type')
                ->options([
                    'single' => '1 optie',
                    'multiple' => 'Meerdere opties',
                    'checkbox' => 'Checkbox',
                    'input' => 'Invulveld',
                    'image' => 'Afbeelding kiezen',
                    'file' => 'Upload bestand',
                ])
                ->default('single')
                ->required()
                ->reactive(),
            Select::make('input_type')
                ->label('Input type')
                ->options([
                    'text' => 'Tekst',
                    'numeric' => 'Getal',
                    'date' => 'Datum',
                    'dateTime' => 'Datum + tijd',
                ])
                ->default('text')
                ->visible(fn (Get $get) => $get('type') == 'input')
                ->required(fn (Get $get) => $get('type') == 'input'),
            TextInput::make('min_length')
                ->label('Minimale lengte/waarde')
                ->numeric()
                ->visible(fn (Get $get) => $get('type') == 'input')
                ->required(fn (Get $get) => $get('type') == 'input'),
            TextInput::make('max_length')
                ->label('Maximale lengte/waarde')
                ->numeric()
                ->visible(fn (Get $get) => $get('type') == 'input')
                ->required(fn (Get $get) => $get('type') == 'input')
                ->reactive(),
            TextInput::make('helper_text')
                ->label('Help tekst'),
            TextInput::make('price')
                ->label('Meerprijs van deze extra')
                ->prefix('€')
                ->helperText('Voorbeeld: 10.25')
                ->numeric()
                ->minValue(0.00)
                ->maxValue(10000),
            Toggle::make('required')
                ->label('Verplicht')
                ->columnSpanFull(),
            Repeater::make('productExtraOptions')
                ->relationship('productExtraOptions')
                ->cloneable(fn (Get $get) => $get('type') != 'checkbox')
                ->label('Opties van deze product extra')
                ->reorderable()
                ->orderColumn('order')
                ->visible(fn (Get $get) => $get('type') == 'single' || $get('type') == 'multiple' || $get('type') == 'checkbox' || $get('type') == 'imagePicker' || $get('type') == 'image')
                ->required(fn (Get $get) => $get('type') == 'single' || $get('type') == 'multiple' || $get('type') == 'checkbox' || $get('type') == 'imagePicker' || $get('type') == 'image')
                ->maxItems(fn (Get $get) => $get('type') == 'checkbox' ? 1 : 50)
                ->reactive()
                ->schema([
                    TextInput::make('value')
                        ->label('Waarde')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('price')
                        ->required()
                        ->label('Meerprijs van deze optie')
                        ->prefix('€')
                        ->helperText('Voorbeeld: 10.25')
                        ->numeric()
                        ->minValue(0.00)
                        ->maxValue(10000),
                    mediaHelper()->field('image', 'Afbeelding'),
                    Toggle::make('calculate_only_1_quantity')
                        ->label('Deze extra maar 1x meetellen, ook al worden er meerdere van het product gekocht'),
                ])
                ->columnSpan(2),
        ];
    }
}
