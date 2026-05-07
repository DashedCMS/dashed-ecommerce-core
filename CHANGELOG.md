# Changelog

All notable changes to `Dashed Ecommerce Core` will be documented in this file.

## v4.18.2 - 2026-05-07

### Changed
- `order_products`-blok in `OrderHandledMail` rendert nu een card-layout per product zoals de verlaten-winkelwagen-mail: thumbnail (`firstImage` van Product, fallback op ProductGroup, via `mediaHelper()->getSingleMedia($id, ['fit' => [80, 80]])`), productnaam, aantal en regelbedrag (`€ X,XX`). Iedere kaart linkt door naar `product->getUrl()` (door `wrapTrackedUrl()` zodat klikken in de stats-widget meegeteld worden). Default 4 zichtbaar; daarboven een "+ N ander(e) product(en)"-regel.
- Builder-blok "Bestelde producten (samenvatting)" heeft een nieuw veld "Max. aantal producten in de mail" (numeriek, 1–50, default 4) zodat admins de afkapgrens per stap kunnen instellen.

## v4.18.1 - 2026-05-07

### Fixed
- Test-mail-action op `OrderHandledFlowResource` (de "Test mail naar mij sturen"-knop) toonde altijd de homepage-URL voor `:reviewUrl:` omdat de in-memory `OrderHandledFlowStep` geen `flow_id` of `flow`-relatie meekreeg, waardoor `pickReviewUrl()` nooit werd aangeroepen. De action haalt nu het flow-record (saved OF in-memory uit de live form-state) op via `$livewire->getRecord()`/`$livewire->form->getState()`, hangt 'm via `setRelation('flow', $flow)` aan de step, en vult zo nodig `flow_id` zodat de mail dezelfde resolutie-keten doorloopt als een echte verzending.
- `OrderHandledMail::build()` schrijft de gekozen review-URL nu terug op de `OrderFlowEnrollment` wanneer die rij wel bestond maar `chosen_review_url` leeg was. Dat dekt enrollments aangemaakt vóór v4.16.0 én enrollments waarvan de flow op het moment van inschrijving nog geen `review_urls` had. Volgende stappen voor dezelfde klant gebruiken vervolgens dezelfde URL, zodat het A/B-label per platform consistent blijft.

### Added
- Artisan-command `dashed:backfill-order-flow-enrollment-review-urls` dat alle bestaande enrollments met lege `chosen_review_url` doorloopt en via `flow->pickReviewUrl()` alsnog vult (gewogen draw + Customsetting-fallback). Idempotent — rijen met een gevulde URL blijven onaangeroerd. Geregistreerd in `hasCommands`.
- Migratie `2026_05_07_090000_backfill_chosen_review_url_on_order_flow_enrollments` voert dezelfde backfill éénmalig uit op deploy zodat admins de command niet handmatig hoeven te draaien. `down()` is een no-op.

## v4.18.0 - 2026-05-07

### Added
- Nieuw e-mail-blok `order_products` voor de OrderHandledFlow-stappen. Toont een samenvatting van de bestelde producten in de mail, alleen op basis van `product_id` (`#123 ×2`). Gebaseerd op `order->orderProducts()->whereNotNull('product_id')`. Het blok rendert niets wanneer de bestelling geen orderProducts met `product_id` heeft. Optionele kop-tekst (default "Wat je hebt besteld:") ondersteunt `:variables:`.
- Builder-blok `Bestelde producten (samenvatting)` (icon `heroicon-o-shopping-bag`, `maxItems(1)`) in de stap-builder van `OrderHandledFlowResource`, met één veld "Kop boven de lijst".

## v4.17.1 - 2026-05-07

### Fixed
- `ProductOpenOrdersWidget` en `ProductGroupOpenOrdersWidget` (open orders footer-widgets op de Product- en ProductGroup-edit-pagina) crashten met `Unable to find component: [dashed.dashed-ecommerce-core.filament.widgets.product.product-{group-,}open-orders-widget]`. Beide zijn nu expliciet als Livewire-component geregistreerd in `DashedEcommerceCoreServiceProvider`, zelfde patroon als de OrderHandledFlow-widgets uit v4.16.3.

## v4.17.0 - 2026-05-07

### Added
- **Bijdragen aan de admin samenvatting-mails (framework uit dashed-core v4.5.0).** Vier nieuwe `SummaryContributor`-classes onder `Services\Summary\`, die zich automatisch registreren via `cms()->builder('summaryContributors', ...)` in de boot van de package. Elke contributor levert een sectie aan voor de periodieke samenvatting-mail (dagelijks / wekelijks / maandelijks). Secties worden automatisch overgeslagen wanneer er voor de periode geen relevante data is, zodat admins geen muur van nullen ontvangen.
- `RevenueSummaryContributor` (key `omzet`, default `daily`): aantal betaalde bestellingen, totale omzet en gemiddelde orderwaarde in de periode, plus een tabel met de top 5 best verkochte producten (kolommen Product, Aantal, Omzet). Telt op `Order::isPaid()` met `whereBetween('created_at', ...)` en joint `dashed__order_products` voor de top 5.
- `AbandonedCartSummaryContributor` (key `verlaten_winkelwagens`, default `weekly`): nieuwe inschrijvingen, verzonden mails, klikken op mail-link, gerecoverde bestellingen en gerecoverde omzet uit verlaten-winkelwagen-flows. Gebruikt `whereBetween` op respectievelijk `created_at`, `sent_at`, `clicked_at` en `converted_at` op `dashed__abandoned_cart_emails`.
- `OrderFlowSummaryContributor` (key `order_opvolg_flows`, default `weekly`): totale nieuwe inschrijvingen, geannuleerde inschrijvingen en klikken op review-links in de periode, plus een tabel met per actieve flow (`OrderHandledFlow::is_active = true`) het aantal inschrijvingen en geannuleerden. Telt op `started_at` / `cancelled_at` / `clicked_at`.
- `CustomerMatchSummaryContributor` (key `customer_match`, default `monthly`): nieuwe betaalde orders met e-mail en met telefoon in de periode plus het aantal keer dat Google Ads de feed in de periode heeft opgehaald. Toont alleen aantallen, nooit ruwe e-mailadressen of telefoonnummers (GDPR). Levert geen sectie wanneer het Customer Match-endpoint niet actief is.

## v4.16.5 - 2026-05-07

### Fixed
- `:reviewUrl:`-variabele in `OrderHandledMail` werd leeg gerenderd wanneer er geen flow `review_urls`, geen Customsetting `order_handled_flow_review_url` EN geen enrollment-record was (typisch bij test-mails of de eerste live runs). De knop in de mail kreeg dan een lege `href` en wees nergens naartoe. Toegevoegd: laatste vangnet dat naar `$siteUrl` (Customsetting `site_url` of `config('app.url')`) terugvalt zodat de knop nooit broken is.

## v4.16.4 - 2026-05-07

### Added
- Kolom "In flow / wacht" op de OrderHandledFlow-overzichtstabel (Filament). Toont per flow het aantal actieve inschrijvingen (`cancelled_at IS NULL`) als badge: oranje wanneer er nog mensen in de flow zitten, grijs wanneer er niemand wacht. Sorteerbaar.
- `OrderHandledFlow::activeEnrollments()` relatie (HasMany op `OrderFlowEnrollment` met `cancelled_at IS NULL`) zodat de tabel via `withCount` één query gebruikt en geen N+1 veroorzaakt.

## v4.16.0 - 2026-05-06

### Added
- **A/B-test van review-URLs op order-opvolg flows.** Per flow kunnen nu meerdere review-URLs ingericht worden (bv. Google, KiyOh, WebwinkelKeur) met een per-URL gewicht. Bij elke nieuwe inschrijving kiest de flow gewogen willekeurig 1 URL en legt die vast op de inschrijving (`chosen_review_url_label`, `chosen_review_url`). Alle stappen van de flow voor dezelfde klant gebruiken vervolgens dezelfde URL, zodat conversies per platform telbaar zijn.
- Migratie `2026_05_06_140000_add_review_urls_to_order_handled_flows` voegt JSON-kolom `review_urls` (nullable) toe aan `dashed__order_handled_flows`. Vorm: `[{label, url, weight}, ...]`.
- Migratie `2026_05_06_140100_add_chosen_review_url_to_order_flow_enrollments` voegt `chosen_review_url_label` (string nullable, geïndexeerd) en `chosen_review_url` (string nullable, 2048 tekens) toe aan `dashed__order_flow_enrollments`.
- Nieuwe model-helper `OrderHandledFlow::pickReviewUrl()` doet de gewogen willekeurige trekking en valt terug op de globale Customsetting `order_handled_flow_review_url` als geen URLs zijn geconfigureerd. Returnt `['label' => ?string, 'url' => string]` of `null`.
- Filament-resource `OrderHandledFlowResource` heeft een nieuwe sectie "Review-URLs (A/B test)" met een `Repeater` voor `[label, url, weight]`. Reorderable + collapsible. Helpertekst legt uit dat leeglaten = fallback op de Customsetting.
- Stats-widget `OrderHandledFlowStats` toont per platform een extra kaart (max 5, sortering op aantal inschrijvingen) met per-platform aantal inschrijvingen, aantal vervolg-betalers en vervolg-omzet. Het platform met de hoogste conversieratio krijgt de groene `success`-kleur, de rest blijft grijs (bij 1 platform geen kleur-highlight).
- Inschrijvingen-tabel `OrderHandledFlowEnrollments` heeft een nieuwe kolom "Platform" (badge, hash-gebaseerde stabiele kleur per label) en een SelectFilter op platform-label, dynamisch gevuld met de daadwerkelijk gekozen labels op deze flow.
- Bestelling-instellingen-pagina (`OrderSettingsPage`) heeft een nieuwe sectie "Order opvolg flow" met veld "Standaard review-URL" dat naar `Customsetting('order_handled_flow_review_url')` schrijft, zodat admins de globale fallback via de UI kunnen beheren.

### Changed
- `OrderHandledMail` leest de `:reviewUrl:`-variabele nu primair uit de actieve `OrderFlowEnrollment` voor de combinatie (order, flow). Valt terug op `flow->pickReviewUrl()` (en die helper valt op zijn beurt terug op de globale Customsetting), zodat bestaande sites zonder per-flow URLs blijven werken.
- `QueueOrderFlowEmailsListener` roept `pickReviewUrl()` aan op het moment van inschrijving en bewaart de gekozen URL + label op de enrollment-rij. Zo blijft de gekozen URL stabiel over alle vervolg-stappen voor dezelfde klant.
- `OrderHandledFlowResource::VARIABLES_HELP` vermeldt nu expliciet dat `:reviewUrl:` per inschrijving A/B-getest wordt wanneer er meerdere URLs zijn ingesteld.

## v4.15.0 - 2026-05-06

### Added
- **Statistieken-widget onderaan de OrderHandledFlow edit-pagina** (`OrderHandledFlowStats`). 6 stats-cards: aantal inschrijvingen, actief in flow, geannuleerd (met breakdown per `cancelled_reason` als description), klikken (totaal + uniek + ratio), geconverteerde klanten (klanten die na inschrijving alsnog opnieuw betaalden) en bijbehorende vervolg-omzet.
- **Inschrijvingen-tabel onderaan de edit-pagina** (`OrderHandledFlowEnrollments`). Lijst met alle orders die in de flow zitten of zaten: bestelling-nummer (klikt door naar order-edit), klant + e-mail, ingeschreven op, actief-icon (check / kruis), reden-badge (`Klik op link`, `Afgemeld via link`, `Recent betaalde order`, `Mail mislukt`, `Gemigreerd`), geannuleerd-op kolom (toggleable). Filter "Alleen actieve" + filter op annuleer-reden. Default sort: meest recente inschrijving bovenaan.

## v4.14.1 - 2026-05-06

### Changed
- **Backfill-knop "Toepassen op bestaande bestellingen" op de OrderHandledFlow edit-pagina is nu altijd zichtbaar** (was: alleen wanneer flow `is_active`). Modal-titel en beschrijving zijn nu dynamisch op basis van `flow->trigger_status`, dus voor een `shipped`-flow staat er "fulfillment-status 'Verzonden'" ipv hardgecodeerde "afgehandeld". Als de flow op het moment van uitvoeren niet actief is wordt een waarschuwings-notificatie getoond ipv stilzwijgend over te slaan.
- `EditOrderHandledFlow::afterSave()` deactiveert nu alleen andere flows met dezelfde `trigger_status` ipv alle flows. Daardoor kunnen meerdere actieve flows tegelijk bestaan (bv. één voor `shipped` en één voor `handled`) zonder elkaar uit te zetten. Mirrort het bestaande `booted()`-gedrag op het model.

## v4.14.0 - 2026-05-06

### Added
- **Order opvolg flows op elke fulfillment-status.** De bestaande "Order handled flow" is doorontwikkeld naar een generieke order-opvolg flow die op elke fulfillment-status getriggerd kan worden (`unhandled`, `handled`, `in_treatment`, `packed`, `ready_for_pickup`, `shipped`). De Filament-resource heeft een nieuwe verplichte `Trigger status`-select (default `handled`) zodat per status een aparte flow ingericht kan worden.
- Per `(order, flow)`-combinatie kan nu maximaal 1 inschrijving bestaan, opgeslagen in nieuwe tabel `dashed__order_flow_enrollments` (`order_id`, `flow_id`, `started_at`, `cancelled_at`, `cancelled_reason`). Zo kunnen meerdere flows tegelijk lopen voor dezelfde order zonder elkaar te dwarsbomen.
- Migratie `2026_05_06_120000_add_trigger_status_to_order_handled_flows` voegt de `trigger_status`-kolom toe aan `dashed__order_handled_flows` (default `'handled'`, geïndexeerd).
- Migratie `2026_05_06_120100_create_dashed__order_flow_enrollments_table` maakt de nieuwe enrollments-tabel inclusief unique-index op `(order_id, flow_id)` en index op `cancelled_at`. Backfill-stap koppelt bestaande orders met `handled_flow_started_at IS NOT NULL` aan de (best-effort) actieve flow zodat lopende flows niet stuk gaan na de upgrade.
- Nieuw Eloquent-model `OrderFlowEnrollment` met `belongsTo` naar order en flow. Order-model heeft een nieuwe `enrollments()`-relatie; OrderHandledFlow heeft een omgekeerde `enrollments()` plus een nieuwe statische `getActiveForStatus(string $status)`-helper.

### Changed
- **Event `OrderMarkedAsHandledEvent` is vervangen door `OrderFulfillmentStatusChangedEvent($order, $oldStatus, $newStatus)`**. De event wordt nu gedispatched op elke fulfillment-status-wijziging, niet alleen bij `handled`. Listeners filteren zelf op de juiste `newStatus`. Geen externe consumers gevonden in de monorepo, dus volledig vervangen zonder alias.
- Listener hernoemd van `QueueOrderHandledEmailsListener` naar `QueueOrderFlowEmailsListener` en herschreven om alle actieve flows op te halen die op de nieuwe fulfillment-status getriggerd zijn. Per flow wordt een enrollment-rij aangemaakt en de stappen ingepland; bestaande inschrijvingen worden overgeslagen.
- `SendOrderHandledEmailJob` pre-flight-checks gebruiken nu de enrollment-rij als bron-van-waarheid: gecancelde inschrijvingen breken de stap af, en de fulfillment-status moet nog overeenkomen met de `trigger_status` van de flow (was hardcoded `'handled'`). Cooldown-cancel zet `enrollment.cancelled_at = now()` met `cancelled_reason = 'recent_paid_order'`; mail-failure zet `cancelled_reason = 'mail_failed'`.
- `OrderHandledClickController` cancelt bij klik nu de specifieke `(order, flow)`-enrollment ipv de globale `order.handled_flow_cancelled_at`. De legacy-kolom wordt voor backwards-compat ook nog gezet wanneer de flow met `cancel_on_link_click = true` getriggerd wordt.
- `OrderHandledUnsubscribeController` cancelt nu alle openstaande inschrijvingen voor de order (over alle flows heen) en zet daarnaast nog steeds `order.handled_flow_cancelled_at` voor backwards-compat.
- `BackfillOrderHandledFlowService::run()` werkt nu op `flow->trigger_status` ipv hardcoded `'handled'` en gebruikt de enrollments-tabel als check op dubbele inschrijving.
- `OrderHandledFlow::getActive()` blijft als backwards-compat alias voor `getActiveForStatus('handled')`. De `booted()`-saved-hook deactiveert nog steeds andere actieve flows, maar nu alleen binnen dezelfde `trigger_status`-bucket zodat verschillende statussen los van elkaar actief kunnen zijn.
- Filament-resource `OrderHandledFlowResource`: navigatie-label / model-label hernoemd naar "Order opvolg flows" / "Order opvolg flow", nieuwe `trigger_status`-kolom in de lijsttabel (badge met Dutch label).

### Notes
- De kolommen `dashed__orders.handled_flow_started_at` / `handled_flow_cancelled_at` blijven bestaan voor backwards-compatibele reads. Nieuwe logica gebruikt uitsluitend `dashed__order_flow_enrollments`.

## v4.13.2 - 2026-05-06

### Fixed
- `Call to a member function order() on null` op de "Openstaande bestellingen"-lijst. De `SelectFilter::query()`-callbacks gebruikten parameter-namen `$q` en `$dir`, terwijl Filament v4 de injectie via parameter-NAAM doet (`query`, `data`, `state`). De callbacks kregen daardoor `null` ipv de Builder en het ternary-statement riep `null->whereHas('order', ...)` aan. Beide filters herschreven met de juiste parameter-namen `$query, $data` en een expliciete early-return bij lege filter-waardes.

### Changed
- Resource-naam **"Openstaande orderproducten" hernoemd naar "Openstaande bestellingen"** (model-label, navigatie-label en plural-label). Bestandsnamen van Excel/CSV-exports volgen mee: `Openstaande bestellingen - {site_name} - {datum}[.xlsx|.csv]`.
- **Footer-widget op de bewerk-pagina van een product**: nieuwe `Filament\Widgets\Product\ProductOpenOrdersWidget` toont een tabel met alle order-regels van bestellingen die nog niet zijn afgehandeld waarin dit product voorkomt. Heading bevat een telling (`X regels, Y stuks`). Lege staat met vriendelijke tekst.
- **Footer-widget op de bewerk-pagina van een product-groep**: zelfde concept (`ProductGroupOpenOrdersWidget`) maar bundelt orderregels van alle producten in de groep, met een extra kolom "Product variant" zodat zichtbaar is welke variant gekocht is.

## v4.13.1 - 2026-05-06

### Fixed
- Productie-crash op het admin-dashboard bij de `OrderAttributionStatsWidget`: zowel `SQLSTATE[42S22] Unknown column 'dashed__orders.deleted_at' in 'where clause'` als `Unknown column 'dashed__orders.id' in 'order clause'`. De widget bouwt zijn data via `Order::query()->fromSub(unionAll(...))`, maar (a) de SoftDeletes-scope voegt `WHERE dashed__orders.deleted_at IS NULL` toe en (b) Filament's table-tiebreaker voegt `ORDER BY dashed__orders.id` toe; beide kolommen bestaan niet in de derived `attribution_stats`-tabel. Drie fixes:
  - `withoutGlobalScopes()` op de buitenste Order-query om SoftDeletes te skippen.
  - `getModel()->setTable('attribution_stats')` zodat `qualifyColumn('id')` resolved naar de derived alias ipv `dashed__orders.id`.
  - Dummy `0 as id`-alias in beide unions als veilige fallback.

## v4.13.0 - 2026-05-06

### Added
- Nieuwe Filament-resource `OpenOrderProductResource` onder E-commerce > "Openstaande orderproducten". Toont order-regels van bestellingen waarvan `fulfillment_status = 'unhandled'` is (verzendkosten/payment_costs zijn uitgesloten). Read-only (geen create/edit). Twee tabs: **"Per orderregel"** (default, 1 rij per OrderProduct) en **"Gegroepeerd per product"** (MIN/SUM-aggregatie via een `fromSub`-wrapper zodat MySQL `ONLY_FULL_GROUP_BY` en SoftDeletes-derived-select beide werken). Kolommen: bestelling, product_id, productnaam, sku, aantal, order_origin (badge, sorteerbaar via leftJoin), fulfillment-status, klant, besteld op. Filters: `fulfillment_status` (default `unhandled`) en `order_origin` (multi-select).
- Twee header-actions op de lijst-pagina: **"Exporteer Excel"** (.xlsx) en **"Exporteer CSV"** (.csv) via `maatwebsite/excel`. Bestandsnaam volgens patroon `Openstaande orderproducten - {site_name} - YYYY-MM-DD[.xlsx|.csv]`, met `(gegroepeerd)`-suffix wanneer het gegroepeerde tabblad actief is. De export herbruikt `OpenOrderProductResource::getEloquentQuery()` zodat dezelfde filters gelden.

## v4.12.0 - 2026-05-06

### Added
- Nieuwe Filament-pagina `Pages\Statistics\AttributionStatisticsPage` (navigatie: Statistics > Herkomst statistieken). Toont voor de gekozen periode 4 KPI-kaarten (orders, omzet, met-UTM, zonder-UTM) plus drie naast-elkaar-tabellen met top-bronnen, top-mediums en top-campagnes (orders + omzet + aandeel + gemiddelde order-waarde). Periode-selector identiek aan de bestaande `RevenueStatisticsPage`. Filters op `utm_source`, `utm_medium` en `utm_campaign` met dynamisch geladen options uit de DB. Top-N selector (10/25/50/100). Permission-gated via `view_statistics`.

### Changed
- `Herkomst`-blok op de bestel-detailpagina is nu altijd zichtbaar (was: alleen bij orders met attributie-data). Lege velden tonen "Niet ingesteld" in de Filament infolist zodat admins de feature ontdekken zonder dat ze een UTM-order moeten openen.

## v4.11.2 - 2026-05-06

### Changed
- `AdminOrderConfirmationMail::telegramSummary()` toont nu twee extra zaken in de Telegram-notificatie:
  - **Custom product-options** als sub-bulletjes onder elke productregel. Elke key/value uit `OrderProduct::$product_extras` wordt gerenderd als `   - <name>: <value>` zodat opties als "Waterdicht maken: Ja" direct zichtbaar zijn voor de admin.
  - **Bron**-veld met de attributie uit de UTM-kolommen op de order (`utm_source / utm_medium / utm_campaign`, `/`-gescheiden). Wordt overgeslagen als `utm_source` leeg is, zodat organische / direct-traffic-orders geen kale "Bron"-regel krijgen.

## v4.11.1 - 2026-05-06

### Fixed
- Productie-crash `SQLSTATE[22001]: String data, right truncated: 1406 Data too long for column 'message' at row 1` op `dashed__cart_logs`. De `cart.discount.applied`-log gebruikte `sprintf('Kortingscode "%s" toegepast', $code)` zonder de input te truncaten. Een Google Ads tracker-rewrite stopte een hele product-URL met `gclid` / `gad_source` / `gbraid`-parameters in de discount-querystring van de abandoned-cart recover-link, waardoor die URL als kortingscode werd toegepast (en geweigerd, en gelogd) terwijl de logregel niet meer in de VARCHAR(255) `message`-kolom paste.
- Drie defensieve fixes:
  - `CartActivityLogger::discountApplied()` truncate de code nu naar 60 chars met `mb_strimwidth(..., '...')` voor de message; volledige code blijft in de json `data`-kolom.
  - `CartController::restoreCart()` accepteert alleen nog `?discount=`-waardes die voldoen aan `^[A-Za-z0-9_\-]{1,64}$`; bagger uit query-string-rewrites belandt niet meer in de sessie.
  - Migratie `2026_05_06_090000_widen_cart_logs_message_to_text` zet `message` van VARCHAR(255) naar TEXT als safety-net voor toekomstige verbose log-events.

## v4.11.0 - 2026-05-05

### Added
- **UTM- en attributie-tracking voor carts en orders.** Nieuwe migratie `2026_05_05_180000_add_attribution_to_carts_and_orders` voegt aan zowel `dashed__carts` als `dashed__orders` de kolommen `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`, `gclid`, `fbclid`, `msclkid`, `landing_page`, `landing_page_referrer`, `attribution_first_touch_at`, `attribution_last_touch_at` en `attribution_extra` (json) toe. Indexen op `(utm_source, utm_medium)` en `utm_campaign` voor filtering.
- Nieuwe middleware `Dashed\DashedEcommerceCore\Http\Middleware\CaptureAttributionMiddleware` (geregistreerd via `cms()->builder('frontendMiddlewares')`) leest UTM-parameters en click-IDs uit de querystring, slaat deze op in de sessie als `dashed_attribution.first_touch` / `last_touch`, en bewaart ook bij organisch / direct verkeer minimaal de landingspagina.
- Nieuwe service `Services\Attribution\AttributionTracker` met `captureFromRequest`, `pullFromSession`, `attachToCart`, `attachToOrder` en `clearSession`. Cart-model heeft een `created`-listener die de service aanroept; Order-model heeft een vangnet in `creating()` dat de attributie via cart of sessie alsnog vult als de aanroepende code dit niet zelf doet. De Checkout-livewire-component roept `attachToOrder` expliciet aan na `Order::save()` zodat de waardes voor `OrderMarkedAsPaidEvent` op de order staan.
- Filament-admin: nieuwe sectie "Herkomst" op de bestel-detailpagina (livewire-component `AttributionInformationList`), nieuwe `SelectFilter`s voor `utm_source`, `utm_medium` en `utm_campaign` op de orders-lijstweergave, en drie toggleable kolommen "Bron", "Medium", "Campagne". Sectie wordt verborgen op orders zonder attributie-data.
- Nieuwe widget `Filament\Widgets\Statistics\OrderAttributionStatsWidget` toont top-bronnen en top-campagnes van de afgelopen 30 dagen op het dashboard (gecombineerde tabel met type-badge, aantal bestellingen en omzet).
- `OrderListExport` voegt 11 nieuwe kolommen toe (UTM-velden, click-IDs, landingspagina, first/last-touch).
- `OrderSettingsPage` heeft een nieuwe sectie "UTM- / herkomst-tracking" met toggles `attribution_tracking_enabled` (default ON, master kill-switch voor de middleware) en `attribution_show_on_invoice` (default OFF, customsetting opgeslagen voor toekomstige invoice-template integratie).
- Tests: `tests/Unit/Services/Attribution/AttributionTrackerTest.php` en `tests/Feature/Middleware/CaptureAttributionMiddlewareTest.php`.

## v4.10.0 - 2026-05-05

### Changed
- **Backfill verwerkt e-mails nu per-job in plaats van in 1 grote loop**. Nieuwe `Jobs\SyncEmailToApiJob` doet 1 API-call per (email, api)-combi en released zichzelf bij rate-limit. `BackfillApiSubscriptionsJob` is nu puur een dispatcher: verzamelt unieke tuples uit alle bronnen en dispatcht 1 `SyncEmailToApiJob` per (email × api). Voordeel: een rate-limit op 1 e-mail vertraagt alleen die specifieke job, andere jobs in de queue lopen door. Faillure-isolation per e-mail. Jobs worden 100ms per-stap gestaggered om een thundering herd richting de API te voorkomen wanneer de queue meerdere workers heeft.

## v4.9.3 - 2026-05-05

### Added
- Backfill-modal heeft nieuwe `CheckboxList` "Order-origins" met opties uit distinct `dashed__orders.order_origin`. Default: alle behalve `Bol` (Bol-bestellingen worden standaard uitgesloten omdat marktplaats-emails zelden marketing-consent hebben). `BackfillApiSubscriptionsJob` constructor heeft nieuwe `array $orderOrigins = []` param; `collectFromOrders()` doet `whereIn('order_origin', $orderOrigins)` als de array gevuld is en de kolom bestaat. Andere bronnen (carts, popup_views, form_inputs, users) zijn niet beïnvloed.

## v4.9.2 - 2026-05-05

### Added
- `BackfillApiSubscriptionsJob` honoreert nu rate-limit signalen van een API. Als `syncEmail()` `['status' => 'rate_limited', 'retry_after' => N]` returnt, doet de job `release(N + 5)` zonder de huidige attempt te loggen, zodat hij na de delay opnieuw start en via de bestaande `alreadyLogged`-check verdergaat waar hij gebleven was. `tries` is verhoogd naar 200 om voldoende releases toe te staan voor grote backfills.

## v4.9.1 - 2026-05-05

### Fixed
- `SendOrderHandledEmailJob` en `SendAbandonedCartEmailJob` falen niet meer als de mail-transport een hard error geeft (bijv. Postmark `406 Inactive recipient` voor adressen met hard bounce of spam complaint). De error wordt gerapporteerd via `report()` + `Log::warning`, de flow wordt voor die ontvanger gecanceld (`handled_flow_cancelled_at` resp. `AbandonedCartEmail::cancelPendingForEmail()`) zodat vervolgstappen niet opnieuw geprobeerd worden, en de job zelf rondt succesvol af zodat hij niet eindeloos door de queue retried wordt.

## v4.9.0 - 2026-05-05

### Added
- **Per-API `sync_always` toggle op de Order-APIs Repeater** in `OrderSettingsPage`. Standaard wordt een geconfigureerde API alleen aangeroepen wanneer de klant in de checkout marketing-toestemming heeft gegeven (`Order::$marketing = true`). Met deze toggle kun je per API forceren dat hij ook zonder marketing-toestemming aangeroepen wordt - handig voor accounting / CRM-integraties die niet onder marketing-consent vallen. `OrderMarkedAsPaidEvent::handle()` checkt nu `($order->marketing || ! empty($api['sync_always']))` per geconfigureerde API in plaats van een globale `if ($order->marketing)`-loop.
- **Bestaande e-mails synchroniseren (backfill)**: nieuwe header-action `Action::make('backfillApiSubscriptions')` op `OrderSettingsPage` die de geconfigureerde Order-APIs alsnog vult met e-mails uit reeds aanwezige bronnen. Modal met `CheckboxList` voor APIs (default: alle), `CheckboxList` voor bronnen (default: alle aanwezige tabellen, gediscoverd via `Schema::hasTable()` + `Schema::hasColumn()`), `Toggle` "Alleen waar marketing-toestemming aanwezig is" (default OFF), en `TextInput` voor batchgrootte (default 50, min 1, max 500). Op confirm wordt `BackfillApiSubscriptionsJob` gequeued. Beschikbare bronnen: `dashed__orders`, `dashed__carts`, `dashed__popup_views` (waar `submitted_at IS NOT NULL`), `dashed__form_input_fields` (gefilterd op `input_type='email'` of veldnaam bevat 'email'), `users`.
- **`Contracts\SupportsEmailBackfill` interface** met `public static function syncEmail(string $email, ?string $firstName, ?string $lastName, array $api): array`. Returnwaarde: `['status' => 'success'|'failed'|'skipped', 'error' => ?string]`. Provider-packages (`dashed-laposta`, `dashed-ternair`) implementeren deze interface op hun bestaande `NewsletterAPI` class.
- **`Models\ApiSubscriptionLog`** + tabel `dashed__api_subscription_logs` (`id, email, api_class, source, status, error, synced_at, created_at, updated_at`). Composite index op `(email, api_class)` voor backfill-dedupe + losse indexen op `email`, `api_class`, `source`, `status`. Source-enum: `order|cart|popup|form|user|backfill`. Status-enum: `success|failed|skipped`. `record()` static helper voor uniforme insert.
- **`Jobs\BackfillApiSubscriptionsJob`** (`ShouldQueue`, timeout 3600s). Verzamelt unieke `(email, voornaam, achternaam)`-tuples uit de gekozen bronnen, lowercases + valideert e-mails, last-seen first/last name wint. Per API + per email: skip wanneer er al een `success`/`skipped` log is voor `(email, api_class)`, anders roep `$apiClass::syncEmail()` aan. Catch-all rond elke call met `report($e)` + `failed`-log. Loggt eindstats (`unique_emails, apis, processed, success, failed, skipped`) naar `Log::info`.
- **Live-flow audit-trail**: elke `$api['class']::dispatch($order, $api)`-call in `OrderMarkedAsPaidEvent` schrijft nu ook een `ApiSubscriptionLog`-row met `source='order'` (status `success` of `failed` + error message bij exception). Backfill + live-flow leveren zo één uniform log.
- **Order-handled opvolg-flow**: nieuwe geautomatiseerde mailflow die start zodra een bestelling op `fulfillment_status = 'handled'` wordt gezet. Mirror van `AbandonedCartFlow` + `PopupFollowUpFlow`, maar getriggerd op afhandeling ipv betaalmoment.
  - Nieuw event `OrderMarkedAsHandledEvent($order)` wordt gedispatcht door `Order::changeFulfillmentStatus()` zodra de status op `'handled'` springt en `handled_flow_started_at IS NULL`.
  - Listener `QueueOrderHandledEmailsListener` resolved de actieve `OrderHandledFlow`, zet `handled_flow_started_at = now()` en queue't een `SendOrderHandledEmailJob` per stap met `delay(now()->addMinutes($step->send_after_minutes))`.
  - `SendOrderHandledEmailJob` doet pre-flight checks: skip wanneer order weg is of niet meer `handled`, wanneer `handled_flow_cancelled_at` gezet is (klik / unsubscribe), wanneer flow of stap inactief is, of wanneer dezelfde klant in de cooldown-periode al een nieuwe betaalde bestelling heeft (`Order::isPaid()`).
  - Mailable `OrderHandledMail` rendert blokken (heading, paragraph, button, image, divider, usp, discount) via dezelfde renderpipeline als `PopupFollowUpMail`, gewrapped in `dashed-core::emails.layout`. Onderwerp + blokken zijn translatable JSON met UUID-keyed blocks. Variabele-substitutie: `:siteName:`, `:siteUrl:`, `:orderNumber:`, `:customerName:`, `:firstName:`, `:discountCode:`, `:discountValue:`, `:reviewUrl:`.
  - Click-tracking: knop- en afbeelding-link-URLs worden gewrapped via signed route `dashed.frontend.order-handled.click`. `OrderHandledClickController` logt naar `dashed__order_handled_clicks`, zet bij `cancel_on_link_click=true` de `handled_flow_cancelled_at`, en redirect naar de oorspronkelijke URL.
  - Afmeld-link onderaan elke mail via signed route `dashed.frontend.order-handled.unsubscribe`. `OrderHandledUnsubscribeController` zet `handled_flow_cancelled_at` en toont de bestaande bevestigingspagina.
  - Filament-resource `OrderHandledFlowResource` met repeater per stap (test-mail-per-stap actie via paper-airplane icon), `ListOrderHandledFlows` met "Maak standaard flow aan" knop, en `EditOrderHandledFlow` met "Toepassen op bestaande" backfill-actie.
  - Service `BackfillOrderHandledFlowService::run(?$flow, $sinceDays)` plant alsnog stappen voor `handled` orders binnen X dagen die nog niet in de flow zitten. Levert stats over `orders_started`, `orders_skipped_*` en `emails_dispatched`.
  - `OrderHandledFlow::createDefault()` maakt een 1-staps flow aan (14 dagen, `send_after_minutes=20160`, default onderwerp "Hoe vond je je bestelling?", blocks: heading + paragraph + button) met UUID-keyed blocks.
  - Migrations: `dashed__order_handled_flows`, `dashed__order_handled_flow_steps` (cascadeOnDelete + index op flow_id+sort_order en flow_id+is_active), `dashed__order_handled_clicks` (FK's op orders + flow_steps), en de extra kolommen `handled_flow_started_at` / `handled_flow_cancelled_at` op `dashed__orders`.

## v4.8.8 - 2026-05-04

### Changed
- **Verlaten-winkelwagen-mails tonen nu alleen items met een geldige `product_id`**. `CartAbandonedSource::items()` en `CancelledOrderAbandonedSource::items()` filteren custom-line-items / verwijderde producten uit de mail-content (en uit de threshold-totalen). Items zonder gekoppeld product zijn niet via een product-URL terug te vinden in de webshop, dus ze in de mail tonen levert alleen broken images en dode links op.
- **Recover-button kopieert nu de items uit de abandoned cart naar de actieve cart van de bezoeker** ipv de cookie te swappen naar de oude cart. `CartController::restoreCart()` doet `cartHelper()->emptyCart()` op de huidige cart en voegt vervolgens elk item van de abandoned cart toe via `cartHelper()->addToCart($product_id, $quantity, $options)`. Items zonder `product_id` of zonder bestaand product worden overgeslagen (consistent met de mail-filter). De gebruiker behoudt zijn eigen cart-context; alleen de inhoud wordt gerestaureerd.

## v4.8.7 - 2026-05-04

### Changed
- Afmeld-link onderaan abandoned-cart-mails toont nu simpel **"Afmelden"** ipv "Afmelden voor verlaten-winkelwagen-mails". Geldt voor zowel de unified layout-fallback als de aparte `abandoned-cart.blade.php` view.

## v4.8.6 - 2026-05-04

### Added
- **Cooldown-check voor verlaten-winkelwagen-mails**: nieuwe kolom `dashed__abandoned_cart_flows.skip_if_paid_within_days` (smallint unsigned, default 30, nullable). `SendAbandonedCartEmailJob::handle()` checkt vlak voor het versturen of de ontvanger in de afgelopen N dagen een betaalde bestelling heeft (`Order::isPaid()` scope: `paid` / `partially_paid` / `waiting_for_confirmation`). Zo ja: alle nog niet verzonden mails voor dat e-mailadres worden gecanceld via `cancelPendingForEmail($email, 'recent_paid_order')` en de huidige send wordt overgeslagen. `null`/`0` zet de check uit.

### Fixed
- `SendAbandonedCartEmailJob` riep `new AbandonedCartMail($cart, $step, ...)` aan terwijl de constructor `AbandonedCartEmail $record` als eerste argument verwacht (TypeError). Doorgeven van `$record` ipv `$cart`.
- `FlowStepsRelationManager` test-send-actie had hetzelfde probleem (TypeError: "Argument #1 (\$record) must be of type AbandonedCartEmail, Cart given"). Bouwt nu een transient `AbandonedCartEmail` op met `cart_id` + `flow_step_id` + `email` + `trigger_type='cart_with_email'` zodat `AbandonedCartSourceResolver::for()` correct resolved naar `CartAbandonedSource`.

## v4.8.5 - 2026-05-04

### Added
- **Afmeld-link onderaan elke abandoned-cart-mail**. Nieuwe signed route `dashed.frontend.abandoned-cart.unsubscribe` op `/abandoned-cart/unsubscribe/{record}` met `AbandonedCartUnsubscribeController`: roept `AbandonedCartEmail::cancelPendingForEmail()` aan met reason `unsubscribed_via_link` zodat alle nog niet verzonden mails voor dat e-mailadres gecanceld worden, en toont een bevestigingspagina. `AbandonedCartMail::build()` levert nu een `$unsubscribeUrl` (signed) en `$unsubscribeLabel` aan de view; de abandoned-cart blade rendert die als kleine onderlijnde link onderin de footer.

### Fixed
- **Abandoned-cart recover-URL voor cancelled-order trigger** (`OrderRecoveryController::resume()`) redirectte naar hardcoded `/checkout` ipv de configured checkout-page. Nu wordt `ShoppingCart::getCheckoutUrl()` gebruikt met fallback naar `getCartUrl()` -> `/`. Daarnaast worden `product_extras` en `hidden_options` van OrderProduct nu meegenomen naar `cartHelper()->addToCart()` zodat producten met extras correct in de cart belanden.
- `CartController::restoreCart()` (cart-with-email trigger) gebruikt nu dezelfde fallback-keten voor de redirect-URL zodat een ontbrekende `checkout_page_id`-customsetting niet meer leidt tot een redirect naar `#`.

## v4.8.4 - 2026-05-04

### Added
- **Knop "Gegevens uit bestelling kopiëren" op de POS klantgegevens-form** naast de bestaande Account-Select. Een tweede searchable Select staat eronder die zoekt over `Order` op `first_name`, `last_name`, `email`, `invoice_id`, `company_name` en `phone_number` (max 50 resultaten, sorted op meest recent). Suffix-action "Gegevens invoeren" haalt alle klant- + adres- + factuuradres-velden uit de geselecteerde bestelling en vult ze in de form (huidige niet-lege waarden blijven behouden via een per-veld `pick`-fallback). De selectie koppelt de bestelling niet aan een account; het is puur een snelle copy-helper.
- Nieuwe public Livewire-property `POSPage::$loadFromOrderId` als state-binding voor het zoekveld.

## v4.8.3 - 2026-05-04

### Fixed
- **Verzendmethodes worden niet meer ten onrechte als "niet geldig" weergegeven** wanneer de verzendkosten van de geselecteerde methode het cart-totaal over de free-shipping drempel duwen. `ShoppingCart::getAvailableShippingMethods()` filtert nu op `cartHelper()->getTotal() - cartHelper()->getShippingCosts()` ipv kale `getTotal()`. Voorheen veroorzaakte de inclusie van shipping in de drempel-check een circulaire afhankelijkheid: als product=EUR 30 en betaalde verzending=EUR 5 met free-shipping vanaf EUR 50, en de admin had ook een free-delivery methode met `minimum_order_value=50` ingesteld, dan voldeed het cart-total `30+5=35` niet aan de minimum-check van 50 totdat de gebruiker eerst free-delivery selecteerde - maar die was niet beschikbaar zolang `getTotal()` shipping mee-tellt. Nu is de drempel-check independent van de huidige selectie.

## v4.8.2 - 2026-05-03

### Changed
- `dashed__discount_codes.discount_percentage` kolom-type omgezet van `integer` naar `decimal(5,2)` zodat decimale kortingspercentages (bijv. 12.5%) ondersteund worden. `DiscountCode::$casts` heeft `'discount_percentage' => 'decimal:2'` gekregen. Migration: `2026_05_03_150000_change_discount_code_percentage_to_decimal.php`. Bestaande integer-waarden blijven werken (12 wordt 12.00).
- Em-dashes (U+2014) verwijderd uit alle source-bestanden, blade-templates en CHANGELOG-entries van deze package.

## v4.8.1 - 2026-05-03

### Changed
- `RemainderPaymentController` rapporteert nu PSP-exceptions via `report($e)` voordat de generieke "geen betaalprovider"-pagina wordt getoond, zodat fouten in `startTransaction()` zichtbaar worden in de error-log/Sentry in plaats van stilzwijgend verborgen achter een statische pagina.

## v4.8.0 - 2026-05-03

### Added
- `PaymentLinkMail` (betaallink naar klant) bevat nu standaard ook het `order-summary` block met productlijst + totalen, niet alleen `order-details`. De klant ziet wat er besteld is + welke prijs, niet alleen factuurnummer.
- Nieuwe `Services\Address\AddressLookup` static service met `lookup($zipCode, $houseNr)` - gedeelde implementatie van PostNL + postcode.tech adres-lookup. Probeert PostNL API eerst (als `checkout_postnl_api_key` is ingesteld), valt terug op postcode.tech (`checkout_postcode_api_key`).
- POS klantgegevens form heeft nu auto-fill voor straat + stad zodra de bezoeker postcode + huisnummer invult (zelfde gedrag als checkout). Werkt voor zowel verzendadres als factuuradres. Live-update via `afterStateUpdated` op zip + huisnummer fields.

### Changed
- POS klantgegevens veld-volgorde: postcode + huisnummer staan nu **vóór** straat + stad (was: straat eerst). Sneller invullen omdat de auto-fill nu direct na postcode + nummer triggered. Idem voor factuur-adres velden.
- AdminOrderConfirmationMail Telegram notification heeft een extra `Kortingscode`-veld dat wordt ingevuld met de gebruikte code + percentage (bij percentage-codes) + bedrag. Wordt overgeslagen als de bestelling geen kortingscode of geen korting heeft.
- `order-summary` email block toont nu de gebruikte kortingscode én percentage in het discount-label (bv. "Korting (TERUG-ABCD1234 - 10%)") naast het bedrag. Geldt voor alle emails die dit block gebruiken (admin order confirmation, payment link, cancelled order, etc.).

## v4.7.15 - 2026-05-03

### Changed
- Quick-add modal: nieuwe self-contained `QuickAddProduct` Livewire-component (eigen view in `resources/views/livewire/quick-add-product.blade.php`) die hero (image + naam + prijs) én filter-UI én toevoegen-knop in 1 component combineert. Modal templates embedden nu deze component. Voorheen werden hero (in CartSuggestions's render) en filters (in AddToCart Livewire) door cross-component events gesynchroniseerd, wat in combinatie met Alpine `x-teleport` morphing-issues opleverde - tweede en volgende filter-clicks updateten de hero niet.
- `CartSuggestions` is opgeschoond: geen `cartSuggestionsVariantChanged` listener of `syncQuickAddVariant` meer; de modal wordt niet meer ge-re-rendered op variant-changes.
- `AddToCart::updated()` dispatcht geen `cartSuggestionsVariantChanged` event meer (was alleen voor de oude cross-component sync).

## v4.7.14 - 2026-05-03

### Fixed
- Quick-add modal: tweede filter-keuze (bv. kleur na maat) updatete de hero niet omdat de `wire:key` op de nested `<livewire:cart.add-to-cart>` `$quickAddProductId` bevatte. Bij elke variant-update dispatchte CartSuggestions een nieuwe productId → key veranderde → AddToCart re-mount → filter-state verloren → tweede klik werkte op een vers gemounte component zonder geheugen van de eerste filter. Nu gebaseerd op `$quickAddGroupId` (stabiel binnen één modal-sessie) zodat AddToCart in leven blijft tijdens het schakelen tussen varianten.

## v4.7.13 - 2026-05-03

### Fixed
- Quick-add modal opent nu met de naam + afbeelding van de geklikte variant ipv de groupnaam + group-firstImage.
- Hero in de modal updatet nu ook bij **partiële** filter-keuzes (bezoeker heeft niet alle filters gezet). `AddToCart::updated()` zoekt het eerste publicShowable product in de group dat de huidige (deel-)selectie matcht en dispatcht dat als preview. Voorheen dispatchte alleen bij volledige match - bij groups met meerdere filters bleef de hero stale tot ALLE filters waren ingevuld.

## v4.7.12 - 2026-05-03

### Added
- **Backfill-knop op AbandonedCartFlow.** Op de bewerk-pagina van een flow staat nu de actie "Toepassen op bestaande" die de stappen van de flow alsnog plant voor verlaten winkelwagens (trigger `cart_with_email`) en/of geannuleerde bestellingen (trigger `cancelled_order`) die binnen de afgelopen X dagen vallen (default 30, instelbaar 1–365). Records die voor deze flow al gepland staan worden overgeslagen - handig wanneer je een nieuwe flow aanmaakt of een bestaande aanpast.
- Nieuwe service `Services\AbandonedCart\BackfillFlowService` die de scheduling doet en een statistics-array teruggeeft (`carts_scheduled`, `carts_skipped_existing`, `orders_scheduled`, `orders_skipped_existing`).

## v4.7.11 - 2026-05-03

### Changed
- Quick-add modal hero (image + naam) wisselt nu mee als de bezoeker in de modal een andere variant kiest. `AddToCart::updated()` dispatcht een `cartSuggestionsVariantChanged` Livewire-event met de huidige `$product->id`; `CartSuggestions` luistert en herlaadt zijn `quickAddGroup`-state met de naam en eerste afbeelding van de geselecteerde variant. Voorheen toonde de hero altijd het oorspronkelijk-aangeklikte product.

## v4.7.10 - 2026-05-02

### Fixed
- `<x-cart.add-to-cart>` component-template gebruikt nu `@props` met defaults voor alle verwachte velden (`product`, `filters`, `productExtras`, `extras`, `quantity`, `volumeDiscounts`, `price`, `discountPrice`, `paymentMethods`). Voorheen crashte het component met `Undefined variable $extras` of `$volumeDiscounts` als de wrapper-view (bv. `dashed.cart.add-to-cart`) ze niet expliciet doorgaf - wat normaal in nested-Livewire setups gebeurt zoals de quick-add modal in cart-suggestions.

## v4.7.9 - 2026-05-02

### Fixed
- `Products::getAll()` crashte met `Unknown column 'orderByProductGroups'` als de Customsetting `product_default_order_type` op die PHP-only sort-mode stond. De code ging eerst door de `canFilterOnShortOrColumn` filter (waar `'orderByProductGroups'` niet in zit, dus orderBy werd `''`), maar viel daarna terug op de Customsetting-waarde die zonder validatie naar `->orderBy()` SQL ging. Nu skippen we de SQL `orderBy()` voor PHP-only modes; de bestaande PHP-side `sortBy(productGroup.order)` afhandeling blijft werken.
- Quick-add modal-content stopt nu de click-event-propagatie via Alpine `@click.stop`. Voorheen sloot de cart-popup zichzelf wanneer je in de geteleporteerde quick-add modal klikte, omdat het cart-popup-paneel een `@click.away`-listener heeft die elke klik buiten het paneel detecteert (en de teleport plaatst de modal als `<body>`-kind, dus technisch buiten het paneel).

## v4.7.8 - 2026-05-02

### Fixed
- Quick-add modal opent nu op de variant waarop de bezoeker klikte ipv de goedkoopste variant (valt terug op cheapest als de geklikte variant niet meer in stock is). Voorheen kreeg de bezoeker willekeurig de cheapest variant te zien als startpunt.
- Modal z-index op `2147483647` (max 32-bit int) gezet via inline style om er zeker van te zijn dat-ie boven alle andere overlays staat - `z-[1000]` was niet hoog genoeg in alle theme-contexten.

## v4.7.7 - 2026-05-02

### Changed
- Quick-add modal toont nu een product-hero (image + groupnaam + "vanaf" prijs + link naar productpagina) en herbruikt vervolgens de bestaande `<livewire:cart.add-to-cart>` component voor de filter-pickers en toevoegen-knop. Dat geeft de bezoeker dezelfde variant-keuze UX (kleur/maat/etc) als op de productpagina, in plaats van een grid van alle 88 variant-kaartjes.
- Modal teleporteert via Alpine `<template x-teleport="body">` naar `<body>` zodat-ie altijd full-screen rendert, ook vanuit de cart-popup-overlay (die `transform` heeft en anders fixed-positionering breekt).
- `productAddedToCart` event sluit de quick-add modal automatisch.

### Removed
- De variant-grid en bijbehorende `quickAddVariants` / `quickAddTotalVariants` properties - vervangen door de nested AddToCart component.

## v4.7.6 - 2026-05-02

### Fixed
- Quick-add popup opende niet voor productgroups waarvoor `showSingleProduct()` true gaf, ondanks dat er meerdere variants beschikbaar zijn (de flag is een UX-keuze, geen variant-count). Quick-add controleert nu het werkelijke aantal in-stock variants - bij 2+ opent de modal, bij 1 of 0 gebeurt direct add-to-cart.
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
- Suggestion-kaarten zijn nu volledig klikbaar - linkt naar de productGroup-pagina (of product-pagina voor single-product groups). Eindgebruiker kan daar variant-filters / opties kiezen voordat-ie toevoegt aan cart. De inline "+" knop is verwijderd zodat producten met variants niet zonder optie-keuze in de cart belanden.
- Dedupe-by-product-group kiest nu de **goedkoopste in-range variant** per group (was: best-seller). Drempel-respecterend: voor gap > 0 alleen de cheapest variant binnen [gap × 0.8, gap × 1.5]; gap = 0 → cheapest variant overall.
- Templates tonen `productGroup->fromPrice()` ("Vanaf €X") wanneer de group meerdere variants heeft, anders de exact-prijs van het product. Naam is `productGroup->name` als de group meerdere variants heeft (anders product-naam).

## v4.7.3 - 2026-05-02

### Added
- `CartProductSuggester` filtert nu in elke bron-stap (cross-sell, categorie-fallback, random fallback) op de gap-closing prijsrange. Voorheen kwam er via random fallback een €100 product door bij een gap van €23. Nu pakt de query `whereBetween('current_price', [gap × 0.8, gap × 1.5])` zodat alleen producten in het sweet-spot bereik door de pipeline komen. Sortering binnen range op `total_purchases` (best-sellers eerst). Out-of-range producten alleen als laatste filler als de range onvoldoende verschillende groups oplevert.
- DB-fetches over-fetchen nu (10× limit, min 50) om voldoende verschillende product-groups te dekken - voorkomt dat alle resultaten variants van 1 best-seller group zijn.
- Pool-completion check kijkt naar aantal distinct product-groups in plaats van aantal products. Zo fetches we door tot we genoeg unieke groups hebben voor de eindlimit.

### Changed
- Cart-popup template (`cart-suggestions-popup.blade.php`) toont geen threshold-banner meer - alleen de suggesties strip. De threshold-bar blijft in de cart-popup theme view zelf staan, suggesties komen onder de cart-items zoals gebruikelijk.
- `cart-suggestions-cart.blade.php` heeft de threshold-banner inline (geen `@include` van een partial die in consumer-themes mag ontbreken).

## v4.7.2 - 2026-05-02

### Added
- `CartProductSuggester` dedupliceert nu op `product_group_id` en kiest per groep de variant met de hoogste `total_purchases` (best-seller). Voorkomt dat meerdere varianten van dezelfde productgroep tegelijk in de suggesties verschijnen.

### Fixed
- `CartSuggestions::addToCart()` gaf voorheen alleen `productId + quantity` mee - het cart-item miste daardoor de keys `discountPrice`, `originalPrice`, `options` en `hiddenOptions` die de cart-row blade verwacht. Component bouwt nu hetzelfde `$attributes`-payload als `ProductCartActions::addToCart`.
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
- `cart-popup.blade.php` vervangt zijn inline threshold-banner door `<livewire:cart.cart-suggestions view="popup" />` - de popup-template rendert die nu samen met de suggesties.
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
