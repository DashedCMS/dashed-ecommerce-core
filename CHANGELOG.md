# Changelog

All notable changes to `Dashed Ecommerce Core` will be documented in this file.

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
