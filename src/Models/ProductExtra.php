<?php

namespace Dashed\DashedEcommerceCore\Models;

use Spatie\Activitylog\LogOptions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Spatie\Translatable\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
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

    public function productGroups(): BelongsToMany
    {
        return $this->belongsToMany(ProductGroup::class, 'dashed__product_extra_product_groups', 'product_extra_id', 'product_group_id');
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

    /**
     * Effectieve parent-extraprijs voor een gebruiker. De prijsgroep is
     * leidend (zie Product::priceForUser), daarna een persoonlijke override,
     * anders de basisprijs van de extra.
     */
    public function priceForUser(?\App\Models\User $user = null): float
    {
        if (! $user && auth()->check()) {
            $user = auth()->user();
        }

        // getRawOriginal zodat herhaald resolven idempotent is, ook nadat de
        // component ->price al naar de gebruikersprijs heeft gezet.
        $base = (float) ($this->getRawOriginal('price') ?? 0);

        if ($user && $user->price_group_id) {
            $groupRow = \Illuminate\Support\Facades\DB::table('dashed__product_extra_price_group')
                ->where('price_group_id', $user->price_group_id)
                ->where('product_extra_id', $this->id)
                ->first();

            $resolved = $this->resolveExtraRow($groupRow, $base);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        if ($user) {
            $userRow = \Illuminate\Support\Facades\DB::table('dashed__product_extra_user')
                ->where('user_id', $user->id)
                ->where('product_extra_id', $this->id)
                ->first();

            $resolved = $this->resolveExtraRow($userRow, $base);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return $base;
    }

    protected function resolveExtraRow($row, float $base): ?float
    {
        if (! $row) {
            return null;
        }

        if ($row->price !== null) {
            return (float) $row->price;
        }

        if ($row->discount_percentage !== null) {
            return max(0, $base - ($base * ((float) $row->discount_percentage / 100)));
        }

        return null;
    }

    public static function getFilamentFields(): array
    {
        return [
            TextInput::make('name')
                ->label(__('Naam'))
                ->required()
                ->maxLength(255),
            Select::make('type')
                ->label(__('Type'))
                ->options([
                    'single' => __('1 optie'),
                    'multiple' => __('Meerdere opties'),
                    'checkbox' => __('Checkbox'),
                    'input' => __('Invulveld'),
                    'textarea' => __('Groot tekstveld'),
                    'image' => __('Afbeelding kiezen'),
                    'file' => __('Upload bestand'),
                ])
                ->default('single')
                ->required()
                ->reactive(),
            Select::make('input_type')
                ->label(__('Input type'))
                ->options([
                    'text' => __('Tekst'),
                    'numeric' => __('Getal'),
                    'date' => __('Datum'),
                    'dateTime' => __('Datum + tijd'),
                ])
                ->default('text')
                ->visible(fn (Get $get) => $get('type') == 'input')
                ->required(fn (Get $get) => $get('type') == 'input'),
            TextInput::make('min_length')
                ->label(__('Minimale lengte/waarde'))
                ->numeric()
                ->visible(fn (Get $get) => $get('type') == 'input' || $get('type') == 'textarea')
                ->required(fn (Get $get) => $get('type') == 'input' || $get('type') == 'textarea'),
            TextInput::make('max_length')
                ->label(__('Maximale lengte/waarde'))
                ->numeric()
                ->visible(fn (Get $get) => $get('type') == 'input' || $get('type') == 'textarea')
                ->required(fn (Get $get) => $get('type') == 'input' || $get('type') == 'textarea')
                ->reactive(),
            TextInput::make('helper_text')
                ->label(__('Help tekst')),
            TextInput::make('price')
                ->label(__('Meerprijs van deze extra'))
                ->prefix('€')
                ->helperText(__('Voorbeeld: 10.25'))
                ->numeric()
                ->minValue(0.00)
                ->maxValue(10000),
            Toggle::make('required')
                ->label(__('Verplicht'))
                ->columnSpanFull(),
            Repeater::make('productExtraOptions')
                ->relationship('productExtraOptions')
                ->cloneable(fn (Get $get) => $get('type') != 'checkbox')
                ->label(__('Opties van deze product extra'))
                ->reorderable()
                ->orderColumn('order')
                ->visible(fn (Get $get) => $get('type') == 'single' || $get('type') == 'multiple' || $get('type') == 'checkbox' || $get('type') == 'imagePicker' || $get('type') == 'image')
                ->required(fn (Get $get) => $get('type') == 'single' || $get('type') == 'multiple' || $get('type') == 'checkbox' || $get('type') == 'imagePicker' || $get('type') == 'image')
                ->maxItems(fn (Get $get) => $get('type') == 'checkbox' ? 1 : 50)
                ->reactive()
                ->schema([
                    TextInput::make('value')
                        ->label(__('Waarde'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('price')
                        ->required()
                        ->label(__('Meerprijs van deze optie'))
                        ->prefix('€')
                        ->helperText(__('Voorbeeld: 10.25'))
                        ->numeric()
                        ->minValue(0.00)
                        ->maxValue(10000),
                    mediaHelper()->field('image', 'Afbeelding'),
                    Toggle::make('calculate_only_1_quantity')
                        ->label(__('Deze extra maar 1x meetellen, ook al worden er meerdere van het product gekocht')),
                    Toggle::make('skip_stock')
                        ->label(__('Voorraad niet aanpassen als deze optie gekozen wordt'))
                        ->helperText(__('De voorraad van het product wordt niet afgeboekt bij een bestelling en niet teruggeboekt bij een annulering of retour. Het aantal verkopen blijft wel gewoon meetellen, en het product blijft onbestelbaar als het op is.')),
                ])
                ->columnSpan(2),
        ];
    }

    public function scopeIsGlobal($query): Builder
    {
        return $query->where('global', 1);
    }
}
