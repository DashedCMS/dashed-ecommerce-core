<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages;

use BackedEnum;
use UnitEnum;
use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Services\Recommendations\Context\RecommendationContext;
use Dashed\DashedEcommerceCore\Services\Recommendations\RecommendationPlacement;
use Dashed\DashedEcommerceCore\Services\Recommendations\RecommendationService;

/**
 * Admin debug surface for the recommendation engine. Pick a product + a
 * placement to see the per-strategy breakdown (raw scores, weighted
 * scores, reasons) and the final aggregated ranking. Useful for tuning
 * placement weights and diagnosing "why did product X appear in
 * placement Y?" questions.
 */
class RecommendationsDebugPage extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Overige';
    protected static ?int $navigationSort = 99100;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-beaker';
    protected static ?string $navigationLabel = 'Recommendations debug';
    protected static ?string $title = 'Recommendations debug';
    protected static ?string $slug = 'recommendations/debug';

    protected string $view = 'dashed-ecommerce-core::filament.pages.recommendations-debug';

    public ?int $productId = null;
    public string $placement = 'cart';

    public array $explanation = ['strategies' => [], 'ranking' => []];

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user !== null
            && method_exists($user, 'hasRole')
            && $user->hasRole('super-admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->productId = (int) request('product_id') ?: null;
        $this->placement = (string) (request('placement', 'cart'));
        $this->compute();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('productId')
                ->label('Product')
                ->options(fn () => Product::query()->limit(200)->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->reactive()
                ->afterStateUpdated(fn () => $this->compute()),

            Select::make('placement')
                ->label('Placement')
                ->options(collect(RecommendationPlacement::cases())
                    ->mapWithKeys(fn ($c) => [$c->value => $c->name])
                    ->all())
                ->default('cart')
                ->reactive()
                ->afterStateUpdated(fn () => $this->compute()),
        ]);
    }

    public function compute(): void
    {
        if (! $this->productId) {
            $this->explanation = ['strategies' => [], 'ranking' => []];
            return;
        }

        $product = Product::find($this->productId);
        if (! $product) {
            $this->explanation = ['strategies' => [], 'ranking' => []];
            return;
        }

        try {
            $placement = RecommendationPlacement::from($this->placement);
        } catch (\Throwable) {
            $placement = RecommendationPlacement::Cart;
        }

        $context = RecommendationContext::for($placement)
            ->withCurrentProducts([$product])
            ->withLimit(10)
            ->build();

        $this->explanation = app(RecommendationService::class)->explain($context);
    }
}
