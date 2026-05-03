# Changelog

All notable changes to `Dashed Ecommerce Core` will be documented in this file.

## v4.7.12 - 2026-05-03

### Added
- **Backfill-knop op AbandonedCartFlow.** Op de bewerk-pagina van een flow staat nu de actie "Toepassen op bestaande" die de stappen van de flow alsnog plant voor verlaten winkelwagens (trigger `cart_with_email`) en/of geannuleerde bestellingen (trigger `cancelled_order`) die binnen de afgelopen X dagen vallen (default 30, instelbaar 1–365). Records die voor deze flow al gepland staan worden overgeslagen — handig wanneer je een nieuwe flow aanmaakt of een bestaande aanpast.
- Nieuwe service `Services\AbandonedCart\BackfillFlowService` die de scheduling doet en een statistics-array teruggeeft (`carts_scheduled`, `carts_skipped_existing`, `orders_scheduled`, `orders_skipped_existing`).

## v4.7.11 - 2026-05-03

### Changed
- Quick-add modal hero (image + naam) wisselt nu mee als de bezoeker in de modal een andere variant kiest. `AddToCart::updated()` dispatcht een `cartSuggestionsVariantChanged` Livewire-event met de huidige `$product->id`; `CartSuggestions` luistert en herlaadt zijn `quickAddGroup`-state met de naam en eerste afbeelding van de geselecteerde variant. Voorheen toonde de hero altijd het oorspronkelijk-aangeklikte product.

## v4.7.10 - 2026-05-02

### Fixed
- `<x-cart.add-to-cart>` component-template gebruikt nu `@props` met defaults voor alle verwachte velden (`product`, `filters`, `productExtras`, `extras`, `quantity`, `volumeDiscounts`, `price`, `discountPrice`, `paymentMethods`). Voorheen crashte het component met `Undefined variable $extras` of `$volumeDiscounts` als de wrapper-view (bv. `dashed.cart.add-to-cart`) ze niet expliciet doorgaf — wat normaal in nested-Livewire setups gebeurt zoals de quick-add modal in cart-suggestions.

## v4.7.9 - 2026-05-02

### Fixed
- `Products::getAll()` crashte met `Unknown column 'orderByProductGroups'` als de Customsetting `product_default_order_type` op die PHP-only sort-mode stond. De code ging eerst door de `canFilterOnShortOrColumn` filter (waar `'orderByProductGroups'` niet in zit, dus orderBy werd `''`), maar viel daarna terug op de Customsetting-waarde die zonder validatie naar `->orderBy()` SQL ging. Nu skippen we de SQL `orderBy()` voor PHP-only modes; de bestaande PHP-side `sortBy(productGroup.order)` afhandeling blijft werken.
- Quick-add modal-content stopt nu de click-event-propagatie via Alpine `@click.stop`. Voorheen sloot de cart-popup zichzelf wanneer je in de geteleporteerde quick-add modal klikte, omdat het cart-popup-paneel een `@click.away`-listener heeft die elke klik buiten het paneel detecteert (en de teleport plaatst de modal als `<body>`-kind, dus technisch buiten het paneel).

## v4.7.8 - 2026-05-02

### Fixed
- Quick-add modal opent nu op de variant waarop de bezoeker klikte ipv de goedkoopste variant (valt terug op cheapest als de geklikte variant niet meer in stock is). Voorheen kreeg de bezoeker willekeurig de cheapest variant te zien als startpunt.
- Modal z-index op `2147483647` (max 32-bit int) gezet via inline style om er zeker van te zijn dat-ie boven alle andere overlays staat — `z-[1000]` was niet hoog genoeg in alle theme-contexten.

## v4.7.7 - 2026-05-02

### Changed
- Quick-add modal toont nu een product-hero (image + groupnaam + "vanaf" prijs + link naar productpagina) en herbruikt vervolgens de bestaande `<livewire:cart.add-to-cart>` component voor de filter-pickers en toevoegen-knop. Dat geeft de bezoeker dezelfde variant-keuze UX (kleur/maat/etc) als op de productpagina, in plaats van een grid van alle 88 variant-kaartjes.
- Modal teleporteert via Alpine `<template x-teleport="body">` naar `<body>` zodat-ie altijd full-screen rendert, ook vanuit de cart-popup-overlay (die `transform` heeft en anders fixed-positionering breekt).
- `productAddedToCart` event sluit de quick-add modal automatisch.

### Removed
- De variant-grid en bijbehorende `quickAddVariants` / `quickAddTotalVariants` properties — vervangen door de nested AddToCart component.

## v4.7.6 - 2026-05-02

### Fixed
- Quick-add popup opende niet voor productgroups waarvoor `showSingleProduct()` true gaf, ondanks dat er meerdere variants beschikbaar zijn (de flag is een UX-keuze, geen variant-count). Quick-add controleert nu het werkelijke aantal in-stock variants — bij 2+ opent de modal, bij 1 of 0 gebeurt direct add-to-cart.
- Badges in checkout en popup templates zeiden "FREE" wat verwarrend kon overkomen alsof het product gratis is. Vervangen door `Translation::get('cart.suggestions.gap_closer_badge_short', 'cart', 'Gratis verz.')` met Nederlandse fallback. Cart-template badge default ook `Gratis verzending` ipv `GRATIS VERZ`.

### Added
- Quick-add modal toont maximaal 12 variants in een grid; voor groups met meer wordt een "Bekijk alle :count: varianten"-link naar de productGroup-pagina toegevoegd onderaan de modal.

## v4.7.5 - 2026-05-02

### Added
- Suggestion-kaarten hebben hun "+"-knop terug. Klik op de kaart navigeert nog steeds naar de productGroup-pagina; klik op de "+" opent een quick-add modal binnen het component met een grid van alle in-stock varianten van die group (image + naam + filter-waarden zoals kleur/maat + prijs + Toevoegen-knop).
- Voor productGroups met `showSingleProduct()=true` (1 variant of `only_show_parent_product`) doet "+" direct add-to-cart zonder modal.
- Modal sluit automatisch nadat een variant is toegevoegd; achtergrond-klik en `&times;`-knop sluiten ook.

### Changed
- `CartSuggestions::openQuickAdd($productId)` → opent modal of doet quick-add. `closeQuickAdd()` resets state. Component bewaart `quickAddGroupId`, `quickAddGroup` en `quickAddVariants` als publieke properties zodat ze in de blade gebruikt kunnen worden.

## v4.7.4 - 2026-05-02

### Changed
- Suggestion-kaarten zijn nu volledig klikbaar — linkt naar de productGroup-pagina (of product-pagina voor single-product groups). Eindgebruiker kan daar variant-filters / opties kiezen voordat-ie toevoegt aan cart. De inline "+" knop is verwijderd zodat producten met variants niet zonder optie-keuze in de cart belanden.
- Dedupe-by-product-group kiest nu de **goedkoopste in-range variant** per group (was: best-seller). Drempel-respecterend: voor gap > 0 alleen de cheapest variant binnen [gap × 0.8, gap × 1.5]; gap = 0 → cheapest variant overall.
- Templates tonen `productGroup->fromPrice()` ("Vanaf €X") wanneer de group meerdere variants heeft, anders de exact-prijs van het product. Naam is `productGroup->name` als de group meerdere variants heeft (anders product-naam).

## v4.7.3 - 2026-05-02

### Added
- `CartProductSuggester` filtert nu in elke bron-stap (cross-sell, categorie-fallback, random fallback) op de gap-closing prijsrange. Voorheen kwam er via random fallback een €100 product door bij een gap van €23. Nu pakt de query `whereBetween('current_price', [gap × 0.8, gap × 1.5])` zodat alleen producten in het sweet-spot bereik door de pipeline komen. Sortering binnen range op `total_purchases` (best-sellers eerst). Out-of-range producten alleen als laatste filler als de range onvoldoende verschillende groups oplevert.
- DB-fetches over-fetchen nu (10× limit, min 50) om voldoende verschillende product-groups te dekken — voorkomt dat alle resultaten variants van 1 best-seller group zijn.
- Pool-completion check kijkt naar aantal distinct product-groups in plaats van aantal products. Zo fetches we door tot we genoeg unieke groups hebben voor de eindlimit.

### Changed
- Cart-popup template (`cart-suggestions-popup.blade.php`) toont geen threshold-banner meer — alleen de suggesties strip. De threshold-bar blijft in de cart-popup theme view zelf staan, suggesties komen onder de cart-items zoals gebruikelijk.
- `cart-suggestions-cart.blade.php` heeft de threshold-banner inline (geen `@include` van een partial die in consumer-themes mag ontbreken).

## v4.7.2 - 2026-05-02

### Added
- `CartProductSuggester` dedupliceert nu op `product_group_id` en kiest per groep de variant met de hoogste `total_purchases` (best-seller). Voorkomt dat meerdere varianten van dezelfde productgroep tegelijk in de suggesties verschijnen.

### Fixed
- `CartSuggestions::addToCart()` gaf voorheen alleen `productId + quantity` mee — het cart-item miste daardoor de keys `discountPrice`, `originalPrice`, `options` en `hiddenOptions` die de cart-row blade verwacht. Component bouwt nu hetzelfde `$attributes`-payload als `ProductCartActions::addToCart`.
- Afbeeldingen in alle 3 suggestion-templates (cart, checkout, popup) renderden niet door een verkeerde `method_exists`-check. Vervangen door `<x-dashed-files::image>` met fallback op `productGroup->firstImage`.
- Event-payload `productAddedToCart` geeft nu het Product-model door (zoals andere Livewires doen) ipv alleen `productId`.

## v4.7.1 - 2026-05-02

### Fixed
- `CartProductSuggester` voorraad-filter checkte `stock > 0` (rauw aantal) terwijl shops met externe voorraad-sync `stock=0` houden maar `in_stock=true` zetten. Filter aangepast naar `! use_stock || in_stock` zodat suggesties verschijnen op shops die hun voorraad via een sync regelen. Voorheen gaf de suggester een lege Collection als alle publicShowable producten `stock=0` hadden.

## v4.7.0 - 2026-05-01

### Added
- **Cart product suggestions** met free-shipping threshold boost.
  - Nieuwe `FreeShippingHelper` centraliseert drempel- en progress-berekening (1u-cache op de free-delivery shipping method, fallback op translation).
  - Nieuwe `CartProductSuggester` service met hybride bron-keten (expliciete cross-sell van cart-items + ProductGroup → categorie-match → random fallback) en gap-boost (sweet-spot prijs ∈ [`gap × min_factor`, `gap × max_factor`]) zodat producten die gratis verzending dichten naar voren komen. Pure functie, accepteert `cartProductIds` + `cartTotal` als input.
  - Nieuwe `CartSuggestions` Livewire component (`cart.cart-suggestions`) met 3 templates: cart-pagina (variant A: horizontale strip met threshold-bar), checkout (variant C: strip altijd zichtbaar, banner schakelt onder/boven drempel) en cart popup (variant B: mini-strip in groene threshold-banner). Component leest cart-state via `cartHelper()` en delegeert naar de pure suggester.
  - Herbruikbare `partials/free-shipping-bar.blade.php` partial.
  - 9 nieuwe Customsettings per site in `OrderSettingsPage` tab "Suggesties": master kill-switch, limits per view (cart/checkout/popup), boost-slots, gap-factors, in-stock filter, random fallback.
  - 21 nieuwe Pest tests (7 helper + 8 suggester + 6 livewire feature).

### Changed
- `CartPopup` en `AddedToCart` Livewires gebruiken nu `FreeShippingHelper` ipv inline drempel-berekening. Publieke properties (`freeShippingThreshold`, `freeShippingPercentage`) onveranderd, geen breaking change.
- `cart-popup.blade.php` vervangt zijn inline threshold-banner door `<livewire:cart.cart-suggestions view="popup" />` — de popup-template rendert die nu samen met de suggesties.
- `cart.blade.php` en `checkout.blade.php` krijgen elk een `<livewire:cart.cart-suggestions ...>` op de relevante plek.

## v4.6.4 - 2026-05-01

### Fixed
- `Checkout::fillPrices()` gaf `$this->depositPaymentMethod` ongetypeerd door aan `CartHelper::setDepositPaymentMethod(?int)`. Een Livewire-payload met een array op die untyped property crashte daardoor met een TypeError tijdens checkout. Coercion (numeric → int, anders null) toegevoegd op de call-site.

## v4.6.3 - 2026-04-28

### Fixed
- `ProductResource` stond in de `E-commerce` navigatiegroep terwijl alle andere product-resources (`ProductGroupResource`, `ProductCategoryResource`, ...) in de `Producten` groep zitten. Producten verplaatst naar de `Producten` groep zodat ze weer bij elkaar staan.

## v4.6.2 - 2026-04-28

### Fixed
- `CustomerMatchExporter` query was niet `only_full_group_by` compatible (MySQL strict mode). Vervangen door een subquery die per email `MAX(id)` selecteert en daar de volledige rij van haalt; dat geeft semantisch ook de juiste data (PII van de meest recente bestelling per klant) en werkt zowel onder strict MySQL als SQLite.

## v4.6.1 - 2026-04-28

### Fixed
- `CustomerMatchSettingsPage` was niet geregistreerd in `DashedEcommerceCorePlugin::register()`, waardoor de Filament-route `filament.dashed.pages.customer-match-settings-page` niet bestond en de instellingenlijst crashte met "Route not defined".

## v4.6.0 - 2026-04-28

### Added
- Google Ads Customer Match HTTPS-feed met SHA-256 hashing van PII volgens Google's spec.
  - Singleton settings page op `Instellingen → Google Ads Customer Match` (basic-auth credentials, slug-rotatie, dry-run preview, 30-dagen activity stats).
  - Endpoint: `GET /google-ads/customer-match/{slug}.csv` met `GoogleAdsBasicAuth` middleware (`Hash::check` + constant-time compare, HTTPS-force in production, 401 met realm header).
  - `CustomerMatchHasher` normaliseert email/telefoon (E.164 voor NL/BE/DE/FR/etc.) en namen voor het hashen; country (ISO-2) en zip blijven plaintext zoals Google verwacht.
  - `CustomerMatchExporter` aggregeert `Order::isPaid()` op email-dedup met filters: `min_orders`, `since`, `until`, `countries`.
  - Toegangslog in nieuwe tabel `customer_match_access_logs` (status, IP, user-agent, row count, failure reason).
  - Rate limit 10 requests/min per IP via named RateLimiter `google-ads-customer-match`.
  - Documentatie: `docs/google-ads-customer-match.md` met Google Ads-stappenplan.

## v4.5.6 - 2026-04-27

### Added
- `DashedEcommerceCoreServiceProvider::bootingPackage()` registreert de eigen Filament navigatiegroepen via de nieuwe dashed-core registry: E-commerce (sort 30), Producten (40), Statistics (110), Export (120). Vereist dashed-core v4.2.0+.

## v4.5.5 - 2026-04-26

### Fixed
- Drie order-log-tags hadden geen beschrijving en toonden "ERROR tag niet gevonden" in de timeline:
  - `abandoned-cart.converted`: "heeft de bestelling alsnog afgerond na een verlaten-winkelmand-flow."
  - `order.system.cancelled.mail.send`: "heeft de annulerings mail automatisch verstuurd."
  - `order.system.cancelled.mail.send.failed`: "heeft de annulerings mail automatisch niet kunnen versturen vanwege een fout."

## v4.5.4 - 2026-04-23

### Fixed

- Migration `add_concept_snapshot_to_orders`: `->after('prices_ex_vat')` verwijderd
  omdat `prices_ex_vat` pas in een latere migratie (`...000004`) wordt toegevoegd.
  De migratie faalde op fresh installs met "Column not found: prices_ex_vat".

## 1.0.0 - 202X-XX-XX

- initial release
