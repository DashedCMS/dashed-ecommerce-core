# Recommendation engine

A unified product-recommendation pipeline used across the storefront, checkout, transactional mail, and popups. Strategies emit candidate scores, the central `RecommendationService` aggregates them with placement-specific weights, runs the margin-aware tie-break, and returns a ranked product collection.

## API

```php
use Dashed\DashedEcommerceCore\Services\Recommendations\Context\RecommendationContext;
use Dashed\DashedEcommerceCore\Services\Recommendations\RecommendationPlacement;
use Dashed\DashedEcommerceCore\Services\Recommendations\RecommendationService;

$context = RecommendationContext::for(RecommendationPlacement::Cart)
    ->withCurrentProducts($cart->items->pluck('product'))
    ->withCustomer($cart->user)
    ->withLimit(4)
    ->withExcluded([42, 99])      // optional: never recommend these product ids
    ->withExtra('cart_total', 79.95) // strategy-specific extras (read by GapClosing)
    ->build();

$result = app(RecommendationService::class)->for($context);
$result->products; // Collection<Product>
$result->scores;   // Collection<ProductScore> — score + reasons per product
```

`RecommendationService::for()` always returns a `RecommendationResult`. Exclusions, `public_show = false`, and out-of-stock products (when `use_stock = true`) are filtered post-aggregation.

## Placements

Seven placements are defined on the `RecommendationPlacement` enum. Each has a hard-coded default strategy mix in `RecommendationService::PLACEMENT_DEFAULTS`:

| Placement | Default mix |
| --- | --- |
| `ProductDetail` | FrequentlyBoughtTogether 0.5 + CategoryAffinity 0.3 + CustomManual 0.2 |
| `Cart` | GapClosing 0.6 + FrequentlyBoughtTogether 0.4 |
| `Checkout` | GapClosing 0.7 + FrequentlyBoughtTogether 0.3 |
| `EmailOrderHandled` | FrequentlyBoughtTogether 0.5 + CategoryAffinity 0.5 |
| `EmailAbandonedCart` | FrequentlyBoughtTogether 0.6 + CategoryAffinity 0.4 |
| `EmailPopupFollowUp` | CategoryAffinity 0.7 + FrequentlyBoughtTogether 0.3 |
| `Popup` | CategoryAffinity 0.5 + CustomManual 0.5 |

The four built-in strategies ship registered out of the box:

- **FrequentlyBoughtTogether** — uses the precomputed co-purchase table.
- **CategoryAffinity** — products sharing a category with the context products.
- **CustomManual** — manually curated "related products" set on the product itself.
- **GapClosing** — uses `cart_total` + `shipping_gap` extras to upsell toward free shipping.

## Registering a custom strategy

Implement `Dashed\DashedEcommerceCore\Services\Recommendations\Strategies\RecommendationStrategy` and register the singleton from your service provider's `bootingPackage()`:

```php
cms()->registerRecommendationStrategy(app(MyStrategy::class));

// Restrict to specific placements:
cms()->registerRecommendationStrategy(app(MyStrategy::class), [
    RecommendationPlacement::Cart,
    RecommendationPlacement::Checkout,
]);
```

The interface requires four methods: `key()` (stable snake_case slug used in `PLACEMENT_DEFAULTS`), `appliesTo(RecommendationContext)` (cheap skip-fast guard), `candidates(RecommendationContext)` (Collection<ProductScore>, scores in 0.0–1.0), `defaultWeight()`.

## Placement adapters

### Cart

```blade
<livewire:cart-recommendations view="cart" :limit="4" />
```

`CartRecommendations` Livewire component. Auto-refreshes on `refreshCart` and `productAddedToCart`. Reads cart contents from `cartHelper()` and `FreeShippingHelper::progress()` so `GapClosingStrategy` knows the upsell window.

### Checkout

Same Livewire component, smaller layout:

```blade
<livewire:cart-recommendations view="checkout" :limit="2" />
```

`view="popup"` is also valid for popup-embedded use.

### Product detail

Blade component:

```blade
<x-dashed-ecommerce-core::recommendations.product-detail
    :product="$product"
    :limit="4"
    heading="Misschien vind je dit ook leuk"
/>
```

Respects `Customsetting('recommendations_product_detail_enabled', null, '1')` — flip to `'0'` to disable per site.

### Mailables

Compose `Dashed\DashedEcommerceCore\Mail\Concerns\HasRecommendations` onto any Mailable and call `recommendationsFor($placement, $currentProducts, $limit, $customer)` from the view:

```php
use Dashed\DashedEcommerceCore\Mail\Concerns\HasRecommendations;

class OrderHandledMail extends Mailable
{
    use HasRecommendations;
}
```

```blade
@include('dashed-ecommerce-core::email.recommendations', [
    'products' => $message->recommendationsFor(
        \Dashed\DashedEcommerceCore\Services\Recommendations\RecommendationPlacement::EmailOrderHandled,
        $order->orderProducts->pluck('product')->filter(),
        4,
        $order->user,
    ),
])
```

The same trait is wired into `OrderHandledMail`, `AbandonedCartMail`, and `PopupFollowUpMail`. Templated mails additionally support a `recommendation` block in the email-template block renderer (`case 'recommendation':` in `OrderHandledMail::renderBlock()`).

### Popups

Set the popup target's `match_type = 'recommendation_strategy'` and store the strategy slug in `recommendation_strategy_slug` on the `dashed__popup_targets` row. `PopupTargetingService` then evaluates the strategy against the current request context to decide whether the popup fires. The constant `PopupTarget::MATCH_RECOMMENDATION_STRATEGY = 'recommendation_strategy'` is the canonical value.

## Admin debug

`/admin/recommendations/debug` — super-admin only (`Filament\Pages\RecommendationsDebugPage::canAccess()` requires `hasRole('super-admin')`). Pick a product + placement to see the per-strategy breakdown (raw + weighted scores, reason chips) alongside the final aggregated ranking. Useful for tuning weights and diagnosing "why did product X appear in placement Y?".

Internally calls `RecommendationService::explain(RecommendationContext)`, which returns the per-strategy breakdown next to the final ranking without changing the public `for()` contract.

## Co-purchase precompute

The `FrequentlyBoughtTogether` strategy reads from a precomputed pair table. Rebuild it with:

```bash
php artisan dashed:recommendations:rebuild         # incremental
php artisan dashed:recommendations:rebuild --full  # rebuild everything + prune stale rows
```

Scheduled daily by default. Override the cron via Customsetting:

```php
Customsetting::set('recommendation_copurchase_recompute_cron', '0 3 * * *');
```

The scheduler entry uses `withoutOverlapping()` so a long-running rebuild can never collide with the next tick.
