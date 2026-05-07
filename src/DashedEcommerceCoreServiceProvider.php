<?php

namespace Dashed\DashedEcommerceCore;

use Livewire\Livewire;
use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Gate;
use App\Providers\AppServiceProvider;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Dashed\DashedCore\Classes\Locales;
use Spatie\LaravelPackageTools\Package;
use Filament\Forms\Components\TextInput;
use Illuminate\Console\Scheduling\Schedule;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Forms\Components\Builder\Block;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Commands\MigrateToV3;
use Dashed\DashedEcommerceCore\Commands\SendInvoices;
use Dashed\DashedEcommerceCore\Commands\ClearOldCarts;
use Dashed\DashedEcommerceCore\Commands\PruneCartLogs;
use Dashed\DashedEcommerceCore\Commands\BackfillOrderFlowEnrollmentReviewUrls;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Dashed\DashedEcommerceCore\Commands\CancelOldOrders;
use Dashed\DashedEcommerceCore\Filament\Pages\POS\POSPage;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Cart\Cart;
use Dashed\DashedEcommerceCore\Livewire\Orders\CancelOrder;
use Dashed\DashedEcommerceCore\Livewire\Orders\CreateOrderLog;
use Dashed\DashedEcommerceCore\Commands\SendAbandonedCartEmails;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Account\Orders;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Cart\AddToCart;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Cart\CartCount;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Cart\CartPopup;
use Dashed\DashedEcommerceCore\Livewire\Orders\AddPaymentToOrder;
use Dashed\DashedEcommerceCore\Commands\UpdateProductInformations;
use Dashed\DashedEcommerceCore\Filament\Pages\POS\POSCustomerPage;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Cart\AddedToCart;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Orders\ViewOrder;
use Dashed\DashedEcommerceCore\Livewire\Orders\Infolists\LogsList;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Checkout\Checkout;
use Dashed\DashedEcommerceCore\Livewire\Orders\CreateTrackAndTrace;
use Dashed\DashedEcommerceCore\Commands\RecalculatePurchasesCommand;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Products\Searchbar;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Products\ShowProduct;
use Dashed\DashedEcommerceCore\Livewire\Orders\Infolists\PaymentsList;
use Dashed\DashedEcommerceCore\Middleware\EcommerceFrontendMiddleware;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\POSSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\VATSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Resources\ShippingZoneResource;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Products\ShowProducts;
use Dashed\DashedEcommerceCore\Livewire\Orders\ChangeOrderRetourStatus;
use Dashed\DashedEcommerceCore\Livewire\Orders\Infolists\ViewStatusses;
use Dashed\DashedEcommerceCore\Filament\Resources\PaymentMethodResource;
use Dashed\DashedEcommerceCore\Filament\Resources\ShippingClassResource;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\ProductCards;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\ProductChart;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\ProductTable;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\RevenueCards;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\RevenueChart;
use Dashed\DashedEcommerceCore\Commands\UpdateExpiredGlobalDiscountCodes;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\OrderSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Resources\ShippingMethodResource;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\DiscountCards;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\DiscountChart;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\DiscountTable;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\InvoiceSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\ProductSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderLogTemplateResource;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Categories\ShowCategories;
use Dashed\DashedEcommerceCore\Livewire\Orders\Infolists\OrderProductsList;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\CheckoutSettingsPage;
use Dashed\DashedEcommerceCore\Http\Middleware\CaptureAttributionMiddleware;
use Dashed\DashedEcommerceCore\Livewire\Orders\ChangeOrderFulfillmentStatus;
use Dashed\DashedEcommerceCore\Livewire\Orders\SendOrderConfirmationToEmail;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\ProductGroupCards;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\ProductGroupChart;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\ProductGroupTable;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\OrderCancelSettingsPage;
use Dashed\DashedEcommerceCore\Livewire\Orders\SendOrderToFulfillmentCompanies;
use Dashed\DashedEcommerceCore\Livewire\Orders\Infolists\PaymentInformationList;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\CustomerMatchSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\ActionStatisticsCards;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\ActionStatisticsChart;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\ActionStatisticsTable;
use Dashed\DashedEcommerceCore\Livewire\Orders\Infolists\ShippingInformationList;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\DefaultEcommerceSettingsPage;
use Dashed\DashedEcommerceCore\Livewire\Orders\Infolists\AttributionInformationList;
use Dashed\DashedEcommerceCore\Livewire\Orders\Infolists\CustomerInformationBlockList;
use Dashed\DashedEcommerceCore\Commands\CheckPastDuePreorderDatesForProductsWithoutStockCommand;
use Dashed\DashedEcommerceCore\Filament\Resources\AbandonedCartFlowResource\Widgets\AbandonedCartFlowStats;
use Dashed\DashedEcommerceCore\Filament\Resources\AbandonedCartFlowResource\RelationManagers\FlowStepsRelationManager;

class DashedEcommerceCoreServiceProvider extends PackageServiceProvider
{
    public static string $name = 'dashed-ecommerce-core';

    public function bootingPackage()
    {
        cms()->registerNavigationGroup('E-commerce', 30);
        cms()->registerNavigationGroup('Producten', 40);
        cms()->registerNavigationGroup('Statistics', 110);
        cms()->registerNavigationGroup('Export', 120);

        \Illuminate\Support\Facades\RateLimiter::for('google-ads-customer-match', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by($request->ip() ?? 'unknown');
        });

        \Dashed\DashedEcommerceCore\Classes\OrderOrigins::register('own', 'Webshop', true);
        \Dashed\DashedEcommerceCore\Classes\OrderOrigins::register('pos', 'POS', false);

        $this->registerPopupTemplates();

        cms()
            ->emailBlock('order-details', \Dashed\DashedEcommerceCore\Mail\EmailBlocks\OrderDetailsBlock::class)
            ->emailBlock('order-address', \Dashed\DashedEcommerceCore\Mail\EmailBlocks\OrderAddressBlock::class)
            ->emailBlock('order-methods', \Dashed\DashedEcommerceCore\Mail\EmailBlocks\OrderMethodsBlock::class)
            ->emailBlock('order-note', \Dashed\DashedEcommerceCore\Mail\EmailBlocks\OrderNoteBlock::class);

        cms()
            ->registerMailable(\Dashed\DashedEcommerceCore\Mail\OrderConfirmationMail::class)
            ->registerMailable(\Dashed\DashedEcommerceCore\Mail\AdminOrderConfirmationMail::class)
            ->registerMailable(\Dashed\DashedEcommerceCore\Mail\PreOrderConfirmationMail::class)
            ->registerMailable(\Dashed\DashedEcommerceCore\Mail\AdminPreOrderConfirmationMail::class)
            ->registerMailable(\Dashed\DashedEcommerceCore\Mail\OrderCancelledMail::class)
            ->registerMailable(\Dashed\DashedEcommerceCore\Mail\OrderCancelledWithCreditMail::class)
            ->registerMailable(\Dashed\DashedEcommerceCore\Mail\AdminOrderCancelledMail::class)
            ->registerMailable(\Dashed\DashedEcommerceCore\Mail\OrderConfirmationForFulfillerMail::class)
            ->registerMailable(\Dashed\DashedEcommerceCore\Mail\OrderFulfillmentStatusChangedMail::class)
            ->registerMailable(\Dashed\DashedEcommerceCore\Mail\OrderNoteMail::class)
            ->registerMailable(\Dashed\DashedEcommerceCore\Mail\TrackandTraceMail::class)
            ->registerMailable(\Dashed\DashedEcommerceCore\Mail\ProductOnLowStockEmail::class)
            ->registerMailable(\Dashed\DashedEcommerceCore\Mail\ProductsWithPastDuePreOrderDateMail::class)
            ->registerMailable(\Dashed\DashedEcommerceCore\Mail\FinanceExportMail::class)
            ->registerMailable(\Dashed\DashedEcommerceCore\Mail\FinanceReportMail::class)
            ->registerMailable(\Dashed\DashedEcommerceCore\Mail\OrderListExportMail::class)
            ->registerMailable(\Dashed\DashedEcommerceCore\Mail\ProductListExportMail::class)
            ->registerMailable(\Dashed\DashedEcommerceCore\Mail\PaymentLinkMail::class);

        cms()->registerResourceDocs(
            resource: \Dashed\DashedEcommerceCore\Filament\Resources\AbandonedCartFlowResource::class,
            title: 'Verlaten winkelwagen flows',
            intro: 'Hier beheer je geautomatiseerde herinneringsmails voor klanten die hun winkelwagen hebben achtergelaten zonder af te rekenen.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- Nieuwe flows aanmaken met een herkenbare naam en bijbehorende kortingsprefix.
- Per flow meerdere stappen instellen, zodat klanten op verschillende momenten een herinnering krijgen.
- Een flow activeren via de **Activeer** knop, zodat deze vanaf dat moment automatisch wordt uitgevoerd.
- Bestaande flows bewerken, dupliceren of verwijderen als een campagne is afgelopen.
MARKDOWN,
                ],
            ],
            tips: [
                'Er kan maar een flow tegelijk actief zijn. Als je een nieuwe flow activeert, wordt de vorige automatisch uitgezet.',
                'Gebruik een unieke kortingsprefix per flow, zo kun je later makkelijk zien welke flow welke bestellingen heeft opgeleverd.',
            ],
        );

        cms()->registerResourceDocs(
            resource: \Dashed\DashedEcommerceCore\Filament\Resources\OrderHandledFlowResource::class,
            title: 'Afgehandelde-bestelling flows',
            intro: 'Hier beheer je geautomatiseerde opvolg-mails voor bestellingen die op fulfillment_status = handled (afgehandeld) zijn gezet, bijvoorbeeld een review-verzoek 14 dagen na verzending.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- Een flow aanmaken met meerdere opvolg-stappen, elk met een eigen wachttijd in minuten.
- Per stap een onderwerp en blokken (heading, paragraaf, knop, afbeelding, scheidingslijn, USPs, kortingscode) instellen.
- Een test-mail per stap naar je eigen adres sturen om de inhoud te controleren.
- Een actieve flow op bestaande afgehandelde bestellingen toepassen via de knop **Toepassen op bestaande**.
- Per flow instellen of een klik op een knop of afbeelding-link de rest van de flow voor die bestelling annuleert.
MARKDOWN,
                ],
            ],
            tips: [
                'Slechts één flow kan tegelijk actief zijn. Activeer je een nieuwe flow, dan wordt de vorige automatisch uitgezet.',
                'Gebruik :firstName: of :customerName: in onderwerp en tekst om de mail persoonlijker te maken.',
                'Met de cooldown sla je de mail over wanneer dezelfde klant net een nieuwe bestelling heeft geplaatst, zo voorkom je dat een review-verzoek vlak na een nieuwe aankoop binnenkomt.',
            ],
        );

        cms()->registerResourceDocs(
            resource: \Dashed\DashedEcommerceCore\Filament\Resources\CartResource::class,
            title: 'Winkelwagens',
            intro: 'Hier krijg je inzicht in alle actieve winkelwagens in je webshop, kassa, hand-orders en klant-POS sessies.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- Bekijken welke klanten op dit moment producten in hun winkelwagen hebben staan.
- Filteren op type winkelwagen: web, kassa, hand-order of klant-POS.
- Per winkelwagen zien om welke klant het gaat, welk totaalbedrag erin zit en hoeveel items er zijn toegevoegd.
- Een winkelwagen leeggooien via de **Leeggooien** actie als deze blijft hangen of opgeschoond moet worden.
- Meerdere winkelwagens tegelijk leeggooien via de bulk actie.
MARKDOWN,
                ],
            ],
            tips: [
                'Een winkelwagen leeggooien is onomkeerbaar. Controleer eerst of de klant de bestelling niet alsnog wil afronden.',
            ],
        );

        cms()->registerResourceDocs(
            resource: \Dashed\DashedEcommerceCore\Filament\Resources\DiscountCodeResource::class,
            title: 'Kortingscodes',
            intro: 'Hier beheer je alle kortingscodes en promo codes die klanten kunnen gebruiken tijdens het afrekenen.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- Kortingscodes aanmaken als percentage korting of vast bedrag korting.
- Per code instellen voor welke producten of categorieen deze geldt.
- Een voorraadlimiet instellen, zodat een code bijvoorbeeld maar 100 keer gebruikt kan worden.
- Een limiet per klant instellen, zodat dezelfde klant een code niet eindeloos kan inwisselen.
- Een geldigheidsperiode instellen met start- en einddatum.
- Losse codes aanmaken of in een keer een hele batch unieke codes genereren voor bijvoorbeeld een campagne.
MARKDOWN,
                ],
            ],
            tips: [
                'Bij meerdere websites kun je per code aangeven op welke site hij geldig is.',
                'Een batch aanmaken is ideaal voor campagnes waar iedere klant een unieke code nodig heeft.',
                'Zet een einddatum op je actiecodes om te voorkomen dat ze na de campagne alsnog worden gebruikt.',
            ],
        );

        cms()->registerResourceDocs(
            resource: \Dashed\DashedEcommerceCore\Filament\Resources\FulfillmentCompanyResource::class,
            title: 'Fulfillment bedrijven',
            intro: 'Hier beheer je externe partners die jouw bestellingen inpakken en verzenden namens jouw webshop.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- Fulfillment partners toevoegen met naam en e-mailadres.
- Instellen of orders automatisch naar dit bedrijf worden doorgezet zodra ze betaald zijn.
- Zien hoeveel producten er aan ieder fulfillment bedrijf zijn gekoppeld.
- Bestaande fulfillment partners bewerken of verwijderen als de samenwerking stopt.
MARKDOWN,
                ],
            ],
            tips: [
                'Zet automatische verwerking alleen aan als je er zeker van bent dat de partner direct aan de slag kan met nieuwe bestellingen.',
            ],
        );

        cms()->registerResourceDocs(
            resource: \Dashed\DashedEcommerceCore\Filament\Resources\GiftcardResource::class,
            title: 'Cadeaukaarten',
            intro: 'Hier beheer je alle digitale cadeaukaarten die klanten kunnen kopen en inwisselen in je webshop.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- Cadeaukaarten bekijken met het originele saldo, het gereserveerde bedrag en het bedrag dat al is uitgegeven.
- Handmatig nieuwe cadeaukaarten aanmaken, bijvoorbeeld voor een winactie of als compensatie.
- Een minimum bedrag instellen dat per keer ingewisseld moet worden.
- Bepalen op welke producten of categorieen de cadeaukaart geldig is.
- Het transactielogboek per cadeaukaart inzien om te volgen wanneer en waar hij is gebruikt.
MARKDOWN,
                ],
            ],
            tips: [
                'Het gereserveerde bedrag is het deel dat in een lopende bestelling staat maar nog niet definitief is afgeboekt.',
                'Controleer het transactielogboek als een klant vragen heeft over zijn of haar resterend saldo.',
            ],
        );

        cms()->registerResourceDocs(
            resource: \Dashed\DashedEcommerceCore\Filament\Resources\OrderLogTemplateResource::class,
            title: 'Bestel log templates',
            intro: 'Hier beheer je herbruikbare e-mail templates die je vanuit het bestelling detail scherm naar klanten kunt sturen.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- Templates aanmaken voor veelvoorkomende updates, zoals "betaling ontvangen", "in behandeling" of "verzonden".
- Variabelen gebruiken in de tekst, zodat klantnaam, bestelnummer en andere ordergegevens automatisch worden ingevuld.
- Bestaande templates aanpassen of verwijderen als de boodschap niet meer klopt.
MARKDOWN,
                ],
            ],
            tips: [
                'Deze templates staan niet in het hoofdmenu. Je bereikt ze via de instellingen en gebruikt ze vanaf het bestelling detail scherm.',
                'Test een nieuwe template eerst op een eigen test-bestelling om te zien of alle variabelen goed worden ingevuld.',
            ],
        );

        cms()->registerResourceDocs(
            resource: \Dashed\DashedEcommerceCore\Filament\Resources\OrderResource::class,
            title: 'Bestellingen',
            intro: 'Hier beheer je alle bestellingen die binnenkomen via de webshop, kassa, hand-orders en marketplace integraties.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- Bestellingen bekijken, filteren en doorzoeken op bestelnummer of klantnaam.
- Betaalstatus en fulfillment status volgen via gekleurde badges.
- Een bestelling openen en handmatig wijzigen, bijvoorbeeld voor klantnotities of adresaanpassingen.
- Snelle acties uitvoeren via de **Acties** knop: status wijzigen, bevestigingsmail opnieuw versturen of een log notitie met bijlage toevoegen.
- Per bestelling de factuur of pakbon downloaden.
MARKDOWN,
                ],
                [
                    'heading' => 'Bulk acties',
                    'body' => <<<MARKDOWN
- Meerdere bestellingen tegelijk selecteren om in een keer alle facturen of pakbonnen te downloaden als samengevoegde PDF.
- De fulfillment status van meerdere bestellingen tegelijk wijzigen, bijvoorbeeld als je een batch hebt verzonden.
MARKDOWN,
                ],
            ],
            tips: [
                'Gebruik de filters bovenaan om snel naar openstaande, betaalde of geannuleerde bestellingen te springen.',
                'De badge in de navigatie laat zien hoeveel nieuwe bestellingen er zijn binnengekomen.',
                'Bulk factuur downloads voegen alle PDFs samen tot een document, handig voor de boekhouding.',
            ],
        );

        cms()->registerResourceDocs(
            resource: \Dashed\DashedEcommerceCore\Filament\Resources\PaymentMethodResource::class,
            title: 'Betaalmethodes',
            intro: 'Hier configureer je de betaalmethodes die klanten kunnen gebruiken in de webshop, aan de kassa of bij achteraf betalen.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- Betaalmethodes toevoegen en bepalen voor welke omgeving ze beschikbaar zijn (online, kassa of achteraf).
- Per methode eventuele extra kosten instellen die de klant betaalt.
- Een methode koppelen aan een payment provider, zodat betalingen automatisch worden verwerkt.
- Bij kassa betaalmethodes de gekoppelde PIN terminal selecteren.
- Instellen of een methode een aanbetaling vraagt in plaats van het volledige bedrag.
MARKDOWN,
                ],
            ],
            tips: [
                'Deze pagina staat niet in het hoofdmenu. Je bereikt hem via de betalingen instellingen.',
                'Controleer bij kassa methodes altijd of de juiste PIN terminal gekoppeld is, anders kunnen betalingen niet worden doorgestuurd.',
            ],
        );

        cms()->registerResourceDocs(
            resource: \Dashed\DashedEcommerceCore\Filament\Resources\PricePerUserResource::class,
            title: 'Prijs per gebruiker',
            intro: 'Hier stel je persoonlijke kortingen in voor specifieke klanten op een of meerdere productcategorieen.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- Per klant een vaste korting instellen op een of meerdere productcategorieen.
- Kiezen of het om een vast bedrag korting of een percentage korting gaat.
- Bestaande afspraken aanpassen of verwijderen als de klantprijs verandert.
- Inloggen namens de betreffende klant, zodat je kunt zien wat hij of zij aan prijzen ziet in de webshop.
MARKDOWN,
                ],
            ],
            tips: [
                'Met de inlog-als actie controleer je snel of de klant inderdaad de juiste prijzen ziet op de webshop.',
            ],
        );

        cms()->registerResourceDocs(
            resource: \Dashed\DashedEcommerceCore\Filament\Resources\ProductCategoryResource::class,
            title: 'Product categorieen',
            intro: 'Hier beheer je de categorieen waarin je producten zijn ingedeeld, inclusief de inhoud van de categoriepaginas en SEO instellingen.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- Nieuwe categorieen aanmaken en bestaande bewerken of verwijderen.
- De volgorde van categorieen aanpassen door ze te verslepen.
- Per categorie een eigen pagina inrichten met content blokken zoals tekst, afbeeldingen, banners en productoverzichten.
- SEO instellen per categorie: titel, meta beschrijving en deelafbeelding voor social media.
- Categorieen nesten (subcategorieen) om een duidelijke structuur voor je webshop te maken.
MARKDOWN,
                ],
            ],
            tips: [
                'De volgorde waarin je categorieen hier zet, is de volgorde waarin ze in het menu en overzichten verschijnen.',
                'Vul altijd de SEO velden in, dit helpt je categoriepaginas om beter gevonden te worden.',
            ],
        );

        cms()->registerResourceDocs(
            resource: \Dashed\DashedEcommerceCore\Filament\Resources\ProductCharacteristicResource::class,
            title: 'Product kenmerken',
            intro: 'Hier beheer je herbruikbare kenmerken zoals kleur, maat of materiaal, die je vervolgens aan producten kunt koppelen.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- Nieuwe kenmerken aanmaken die je later bij producten kunt invullen.
- De volgorde van kenmerken aanpassen door ze te verslepen.
- Per kenmerk bepalen of hij zichtbaar is op de webshop of alleen intern wordt gebruikt.
- Interne notities toevoegen die niet zichtbaar zijn voor klanten.
MARKDOWN,
                ],
            ],
            tips: [
                'Zet een kenmerk op verborgen als je hem alleen intern gebruikt, bijvoorbeeld voor leveranciersinformatie.',
                'De volgorde bepaalt hoe kenmerken op de productpagina worden getoond aan klanten.',
            ],
        );

        cms()->registerSettingsDocs(
            page: \Dashed\DashedEcommerceCore\Filament\Pages\Settings\AbandonedCartSettingsPage::class,
            title: 'Verlaten winkelwagen mails instellingen',
            intro: 'Stuur automatisch herinneringen naar klanten die producten in hun winkelwagen hebben gelegd maar de bestelling niet hebben afgerond. Je kunt tot drie mails achter elkaar versturen en de derde mail eventueel voorzien van een kortingscode.',
            sections: [
                [
                    'heading' => 'Wat kun je hier instellen?',
                    'body' => <<<MARKDOWN
Op deze pagina bouw je een reeks van maximaal drie herinner-mails:

- **Mail 1**: een eerste vriendelijke herinnering, meestal een paar uur na het verlaten van de winkelwagen.
- **Mail 2**: een vervolgmail, vaak een dag later. Hier kun je de :product: variabele gebruiken in het onderwerp om het product te tonen.
- **Mail 3**: een laatste poging waarin je optioneel een kortingscode meegeeft om de klant alsnog over de streep te trekken.

Per mail bepaal je hoeveel uren er moeten verstrijken voordat de mail wordt verstuurd. De wachttijd telt vanaf het vorige moment in de flow (bij mail 1 vanaf het verlaten van de winkelwagen, bij mail 2 vanaf het versturen van mail 1, enzovoort).
MARKDOWN,
                ],
                [
                    'heading' => 'Wanneer gebruik je dit?',
                    'body' => <<<MARKDOWN
Verlaten winkelwagen mails zijn een bewezen manier om gemiste omzet terug te halen. Een paar richtlijnen:

- Houd mail 1 kort en behulpzaam, niet pusherig. Vaak helpt het al om de klant er alleen aan te herinneren.
- Geef niet te snel een kortingscode weg. Klanten die regelmatig hun winkelwagen verlaten leren dan dat ze altijd korting kunnen krijgen.
- Test de teksten en timing rustig uit en kijk via je mailstatistieken wat het beste werkt voor jouw doelgroep.
MARKDOWN,
                ],
            ],
            fields: [
                'Mails actief' => 'Hoofdschakelaar voor de hele flow. Zet deze uit om tijdelijk geen herinneringen te versturen, zonder dat je de instellingen van de losse mails kwijt raakt.',
                'Mail 1 vertraging' => 'Aantal uren na het verlaten van de winkelwagen voordat de eerste mail wordt verstuurd. Een waarde tussen 1 en 4 uur werkt voor de meeste webshops het beste.',
                'Mail 1 onderwerp' => 'Onderwerp van de eerste herinnering. Houd het persoonlijk en behulpzaam.',
                'Mail 2 vertraging' => 'Aantal uren na het versturen van mail 1 voordat de tweede mail volgt. Een dag (24 uur) is een gangbare keuze.',
                'Mail 2 onderwerp' => 'Onderwerp van de tweede mail. Je kunt :product: gebruiken om de naam van het achtergelaten product in het onderwerp te tonen.',
                'Mail 3 actief' => 'Schakel de derde mail in als je een laatste poging wilt doen, eventueel met een kortingscode.',
                'Mail 3 vertraging' => 'Aantal uren na het versturen van mail 2 voordat de derde mail wordt verstuurd. Vaak 24 tot 48 uur.',
                'Mail 3 onderwerp' => 'Onderwerp van de derde mail. Maak het urgent maar niet drammerig.',
                'Kortingstype' => 'Kies wat voor korting je in mail 3 aanbiedt. Geen korting, een vast bedrag in euro of een percentage over het totaalbedrag.',
                'Kortingswaarde' => 'Het bedrag of percentage van de korting. Bij een vast bedrag vul je euros in, bij een percentage een getal tussen 1 en 100.',
                'Geldigheid korting' => 'Aantal dagen dat de kortingscode geldig blijft nadat mail 3 is verstuurd. Een korte geldigheid (3 tot 7 dagen) zorgt voor meer urgentie.',
            ],
            tips: [
                'Test de hele flow eerst met je eigen e-mailadres door zelf een winkelwagen te verlaten.',
                'Kijk regelmatig naar de open- en klikpercentages om de timing en onderwerpen verder te verbeteren.',
            ],
        );

        cms()->registerSettingsDocs(
            page: \Dashed\DashedEcommerceCore\Filament\Pages\Settings\CheckoutSettingsPage::class,
            title: '',
            intro: 'Bepaal hoe het afrekenproces eruit ziet voor je klanten. Welke velden zijn verplicht, of er automatisch adressen worden ingevuld, hoe valuta wordt getoond en welke betaal- en verzendmethodes standaard geselecteerd staan. Deze pagina is per site instelbaar, zodat je voor iedere webshop een eigen checkout kunt configureren.',
            sections: [
                [
                    'heading' => 'Wat kun je hier instellen?',
                    'body' => <<<MARKDOWN
De checkout instellingen vallen uiteen in een paar groepen:

- **Verplichte velden**: bepaal of klanten een account moeten aanmaken, of voornaam verplicht is, of een bedrijfsnaam gevraagd wordt en of een telefoonnummer nodig is.
- **Adres aanvullen**: koppel een externe dienst (Google, PostNL of postcode.tech) zodat klanten alleen postcode en huisnummer hoeven in te vullen.
- **Valuta en weergave**: kies hoe bedragen worden getoond en of het valutasymbool zichtbaar is.
- **Standaard selecties**: laat automatisch de eerste betaal- en verzendmethode aanstaan om de checkout sneller te maken.
MARKDOWN,
                ],
                [
                    'heading' => 'Verplichte velden, optioneel of verborgen?',
                    'body' => <<<MARKDOWN
Bij de meeste velden kies je tussen drie opties:

- **Verborgen**: het veld wordt niet getoond aan de klant.
- **Optioneel**: het veld wordt getoond maar de klant hoeft het niet in te vullen.
- **Verplicht**: zonder dit veld kan de klant niet afrekenen.

Vraag alleen wat je echt nodig hebt. Hoe minder velden, hoe minder klanten de checkout halverwege verlaten.
MARKDOWN,
                ],
                [
                    'heading' => 'Welke adres-API kies je?',
                    'body' => <<<MARKDOWN
Adres aanvullen werkt met verschillende leveranciers, afhankelijk van de versie van je checkout:

- **Google Maps**: gebruikt door de oude checkout. Vereist een Google API key met de Places API ingeschakeld.
- **PostNL**: voor de nieuwe checkout. Werkt alleen voor Nederlandse adressen, vereist een PostNL key.
- **postcode.tech**: alternatief voor de nieuwe checkout, ook Nederland en Belgie. Vraag een gratis key aan op postcode.tech.

Je kunt prima maar een van deze drie invullen. De checkout kiest automatisch de juiste op basis van wat beschikbaar is.
MARKDOWN,
                ],
            ],
            fields: [
                'Klantaccount vereiste' => 'Bepaalt of een klant een account moet hebben om te kunnen bestellen. Uit betekent gast-checkout, optioneel laat de klant kiezen, verplicht dwingt registratie af. Verplicht maken verlaagt vrijwel altijd de conversie.',
                'Voor- en achternaam' => 'Kies of de klant alleen een achternaam invult of zowel voornaam als achternaam. Voor B2C webshops is voor en achternaam gebruikelijk.',
                'Bedrijfsnaam' => 'Verberg de bedrijfsnaam helemaal, maak hem optioneel of verplicht hem. B2B webshops zetten dit vaak op verplicht, B2C op verborgen of optioneel.',
                'Telefoon bezorgadres' => 'Een telefoonnummer is vooral handig voor de bezorger. Veel vervoerders gebruiken het om de klant te bellen bij problemen tijdens de bezorging.',
                'Bezorgadres als factuuradres' => 'Aan betekent dat het bezorgadres standaard ook als factuuradres wordt gebruikt. De klant kan dat afwijken als hij wil. Dit scheelt invultijd.',
                'Adres auto-aanvullen' => 'Aan zorgt dat het adres automatisch wordt aangevuld op basis van postcode en huisnummer. Vereist dat een van de adres-API keys hieronder is ingevuld.',
                'Extra scripts bevestigpagina' => 'Extra scripts (bijvoorbeeld voor conversie-tracking) die alleen op de bestel-bevestigingspagina geladen worden. Plak hier de complete code inclusief script tags.',
                'Google Maps API sleutel' => 'Google Maps API key, alleen nodig voor de oude checkout. Vraag de key aan in Google Cloud Console en zet de Places API aan.',
                'PostNL API sleutel' => 'PostNL API key voor het automatisch aanvullen van Nederlandse adressen in de nieuwe checkout. Aan te vragen via je PostNL zakelijke account.',
                'postcode.tech API sleutel' => 'API key van postcode.tech voor het aanvullen van adressen. Een gratis key is voldoende voor de meeste webshops.',
                'BCC e-mailadres' => 'Stuur een blinde kopie van elke orderbevestiging naar dit adres. Handig voor je administratie of als achtervang.',
                'Forceer checkout pagina' => 'Aan betekent dat de klant vanuit de winkelwagen direct naar de checkout pagina gaat in plaats van de eenstaps variant. Gebruik dit als je een uitgebreide checkout pagina hebt ingericht.',
                'Valuta weergave' => 'Bepaalt hoe bedragen worden weergegeven (bijvoorbeeld 12,50 of 12.50 of met punt als duizendtalscheiding).',
                'Valutasymbool tonen' => 'Aan toont het euroteken (of een ander symbool) bij elke prijs. Uit toont alleen het bedrag.',
                'Eerste betaalmethode voorselecteren' => 'Aan zorgt dat de eerste betaalmethode in de lijst standaard al is aangevinkt. Dat kan de checkout sneller maken, maar zet de populairste methode bovenaan.',
                'Eerste verzendmethode voorselecteren' => 'Idem voor verzendmethodes. Handig als er een duidelijke standaard is, bijvoorbeeld bezorging in Nederland.',
            ],
            tips: [
                'Test je checkout zelf met een echte bestelling op een testbetaling, zo zie je precies wat klanten doorlopen.',
                'Hoe minder verplichte velden, hoe hoger je conversie. Vraag alleen wat je echt nodig hebt.',
            ],
        );

        cms()->registerSettingsDocs(
            page: \Dashed\DashedEcommerceCore\Filament\Pages\Settings\DefaultEcommerceSettingsPage::class,
            title: 'Google Merchant Center instellingen',
            intro: 'Koppel je webshop aan Google Merchant Center om producten te tonen in Google Shopping en om Google klantbeoordelingen te verzamelen. Deze instellingen kun je per site instellen.',
            sections: [
                [
                    'heading' => 'Wat kun je hier instellen?',
                    'body' => <<<MARKDOWN
Op deze pagina koppel je de basis-integratie met Google Merchant Center:

- **Merchant Center ID**: het nummer van jouw account, nodig om de juiste winkel te identificeren.
- **Klantbeoordelingen survey**: Google stuurt klanten na een bestelling automatisch een uitnodiging om je winkel te beoordelen.
- **Klantbeoordelingen badge**: het bekende sterren-keurmerk dat je op je website kunt tonen zodra je voldoende reviews hebt verzameld.

Je hebt zelf een actief Google Merchant Center account nodig en moet daar de productfeed en het programma voor klantbeoordelingen aanzetten.
MARKDOWN,
                ],
            ],
            fields: [
                'Merchant Center ID' => 'Het ID van jouw Google Merchant Center account. Te vinden bovenaan in Merchant Center, vaak een nummer van 8 tot 10 cijfers.',
                'Klantbeoordelingen survey' => 'Aan zorgt dat klanten na een bestelling automatisch een mail krijgen van Google met het verzoek om de winkel te beoordelen. Activeer eerst het programma in Merchant Center.',
                'Klantbeoordelingen badge' => 'Aan toont de Google klantbeoordelingen badge op je website zodra je genoeg reviews hebt verzameld (Google hanteert een minimum aantal).',
            ],
        );

        cms()->registerSettingsDocs(
            page: \Dashed\DashedEcommerceCore\Filament\Pages\Settings\InvoiceSettingsPage::class,
            title: 'Factuurnummers instellingen',
            intro: 'Bepaal hoe factuurnummers worden opgebouwd voor deze site. Je kunt kiezen tussen oplopende nummers (vereist door de Nederlandse Belastingdienst voor de meeste situaties) of willekeurige IDs. Per site instelbaar.',
            sections: [
                [
                    'heading' => 'Wat kun je hier instellen?',
                    'body' => <<<MARKDOWN
Een factuurnummer bestaat uit drie onderdelen:

- **Voorvoegsel**: een vast stukje tekst aan het begin, bijvoorbeeld het jaar of een afkorting van je bedrijf.
- **Het nummer zelf**: oplopend (1, 2, 3, ...) of willekeurig (gebaseerd op een patroon).
- **Achtervoegsel**: een vast stukje tekst aan het einde.

Een factuur kan er bijvoorbeeld uitzien als `2026-00123-NL` waarbij `2026-` het voorvoegsel is, `00123` het oplopende nummer en `-NL` het achtervoegsel.
MARKDOWN,
                ],
                [
                    'heading' => 'Oplopend of willekeurig?',
                    'body' => <<<MARKDOWN
Voor de Nederlandse Belastingdienst moeten facturen in principe een doorlopende, opvolgende reeks vormen. Kies daarom in de meeste gevallen voor oplopende nummers.

Willekeurige IDs zijn alleen handig in specifieke situaties, bijvoorbeeld als je facturatie via een ander systeem regelt en alleen unieke kenmerken nodig hebt. Overleg in dat geval met je boekhouder of accountant.
MARKDOWN,
                ],
            ],
            fields: [
                'Willekeurig factuurnummer' => 'Aan zorgt dat factuurnummers willekeurig worden opgebouwd op basis van een patroon. Uit gebruikt een oplopende reeks, wat in de meeste gevallen verplicht is voor de boekhouding.',
                'Willekeurig patroon' => 'Patroon voor willekeurige factuurnummers. Een sterretje (*) wordt vervangen door een willekeurig teken. Bijvoorbeeld `INV-****` levert nummers als `INV-A3K9` op.',
                'Huidig factuurnummer' => 'Het huidige factuurnummer van waaruit verder geteld wordt. Pas dit alleen aan als je weet wat je doet, want bestaande facturen kunnen niet hetzelfde nummer hebben.',
                'Voorvoegsel' => 'Vast stukje tekst aan het begin van het factuurnummer, maximaal 5 tekens. Veel gebruikt: het jaartal of een afkorting.',
                'Achtervoegsel' => 'Vast stukje tekst aan het einde van het factuurnummer, maximaal 5 tekens.',
            ],
            tips: [
                'Overleg met je boekhouder voordat je halverwege een boekjaar de nummering aanpast.',
                'Een voorvoegsel met het jaartal (zoals `2026-`) maakt het terugzoeken van facturen makkelijker.',
            ],
        );

        cms()->registerSettingsDocs(
            page: \Dashed\DashedEcommerceCore\Filament\Pages\Settings\OrderCancelSettingsPage::class,
            title: '',
            intro: 'Bepaal naar welke fulfillment status een bestelling automatisch wordt gezet zodra die wordt geannuleerd. Per site instelbaar.',
            sections: [
                [
                    'heading' => 'Wat kun je hier instellen?',
                    'body' => 'Wanneer een bestelling wordt geannuleerd (door de klant of door jou) moet duidelijk zijn welke fulfillment status de bestelling daarna krijgt. Die status bepaalt onder andere of er nog mails worden verstuurd, of de voorraad wordt teruggeboekt en hoe de bestelling in overzichten verschijnt. Kies hier de status die het beste past bij jouw werkwijze, bijvoorbeeld "Geannuleerd" of "Terugbetaald".',
                ],
            ],
            fields: [
                'Status bij annulering' => 'De fulfillment status waar een bestelling automatisch naartoe gaat zodra die wordt geannuleerd. Zorg dat de gekozen status bestaat in je lijst met fulfillment statussen.',
            ],
        );

        cms()->registerSettingsDocs(
            page: \Dashed\DashedEcommerceCore\Filament\Pages\Settings\OrderSettingsPage::class,
            title: '',
            intro: 'Beheer hoe bestellingen worden binnengehaald, geprint, gemeld en bevestigd aan klanten. Deze pagina bundelt vier onderwerpen: koppelingen met externe verkoopkanalen, printers voor facturen en paklijsten, interne notificatie e-mails per site en mails die klanten ontvangen wanneer hun bestelling van fulfillment status verandert. De notificatie e-mails zijn per site instelbaar en de status-mails zijn per taal te vertalen.',
            sections: [
                [
                    'heading' => 'Wat kun je hier instellen?',
                    'body' => <<<MARKDOWN
De pagina is opgedeeld in vier blokken:

- **Order APIs**: koppelingen met externe verkoopkanalen zoals Bol.com en Channable. Per koppeling voeg je een rij toe met de juiste gegevens.
- **Printers**: configureer de printers voor facturen en paklijsten. Je kunt voor beide een eigen printer kiezen.
- **Notificatie e-mails per site**: bepaal welke interne mailadressen een melding krijgen bij nieuwe bestellingen, lage voorraad of als BCC op alle bestelmails.
- **Fulfillment status mails**: voor elke fulfillment status (zoals "In behandeling", "Verzonden", "Bezorgd") kun je een aparte e-mail aan de klant aanzetten, met een eigen onderwerp en inhoud per taal.
MARKDOWN,
                ],
                [
                    'heading' => 'Printers koppelen',
                    'body' => <<<MARKDOWN
Voor zowel de factuurprinter als de paklijstprinter kies je een verbindingstype:

- **CUPS**: voor printers die via een Linux/Mac CUPS server beschikbaar zijn. Vul de printernaam in zoals die in CUPS bekend staat.
- **Netwerk**: een netwerkprinter die direct op een IP adres aanspreekbaar is. Vul het IP adres of de hostnaam in.
- **Windows**: voor een printer op een Windows server. Vul de gedeelde naam van de printer in.

Laat de instelling leeg als je niet automatisch wilt printen.
MARKDOWN,
                ],
                [
                    'heading' => 'Mails bij wisseling van fulfillment status',
                    'body' => <<<MARKDOWN
Voor elke fulfillment status kun je apart bepalen of er een mail naar de klant gaat. Dat is handig om je klant op de hoogte te houden zonder dat je elke wijziging zelf hoeft te communiceren. Per status leg je vast:

- Of de mail uberhaupt verstuurd wordt (toggle).
- Het onderwerp van de mail.
- De inhoud van de mail (rich text editor).

Omdat je site meertalig kan zijn, voer je deze waarden per taal in. Vergeet niet beide talen te vullen, anders krijgen klanten in een andere taal een lege mail.
MARKDOWN,
                ],
            ],
            fields: [
                'Order API koppelingen' => 'Voeg per externe verkoopkanaal (Bol.com, Channable en dergelijke) een rij toe met de bijbehorende API gegevens. Het soort velden hangt af van de gekozen integratie.',
                'Factuurprinter verbinding' => 'Manier waarop de factuurprinter wordt aangesproken. Kies CUPS voor Linux/Mac printservers, Netwerk voor printers met een eigen IP en Windows voor gedeelde Windows printers.',
                'Factuurprinter naam' => 'De naam, het IP adres of de share van de printer, afhankelijk van het gekozen verbindingstype. Verplicht zodra je een type kiest.',
                'Paklijstprinter verbinding' => 'Verbindingstype voor de paklijstprinter. Werkt op dezelfde manier als de factuurprinter en mag een andere printer zijn.',
                'Paklijstprinter naam' => 'De naam, het IP adres of de share van de paklijstprinter.',
                'Orderbevestiging e-mails' => 'E-mailadressen die een interne kopie krijgen van iedere bestelbevestiging voor deze site. Druk op Enter na elk adres om meerdere te kunnen toevoegen.',
                'Lage voorraad e-mails' => 'E-mailadressen die een melding krijgen zodra een product onder de minimum voorraad zakt. Handig voor inkoop of magazijn.',
                'BCC orderbevestigingen' => 'E-mailadressen die als BCC op alle bestelmails worden meegestuurd. Gebruik dit als je een centrale archiefmailbox hebt.',
                'Fulfillment mail actief' => 'Schakel deze fulfillment status mail in of uit. Per status en per taal kun je een eigen instelling kiezen, dus vergeet niet alle talen te controleren.',
                'Fulfillment mail onderwerp' => 'Onderwerp van de mail die de klant krijgt zodra de bestelling deze status bereikt. Houd het kort en duidelijk, bijvoorbeeld "Je bestelling is verzonden".',
                'Fulfillment mail inhoud' => 'Inhoud van de status-mail. Schrijf hier de boodschap die de klant moet zien. Je kunt opmaak, afbeeldingen en links gebruiken via de editor. Vul deze ook in voor iedere taal die je site ondersteunt.',
            ],
            tips: [
                'Test elke status-mail door zelf een testbestelling door de fulfillment heen te schuiven.',
                'Houd de inhoud van status-mails kort en informatief, klanten lezen ze snel diagonaal.',
                'Zet voor de printers altijd eerst handmatig een testprint klaar voordat je live gaat.',
            ],
        );

        cms()->registerSettingsDocs(
            page: \Dashed\DashedEcommerceCore\Filament\Pages\Settings\POSSettingsPage::class,
            title: '',
            intro: 'Stel het kassasysteem in voor verkoop in een fysieke winkel. Je bepaalt of de kassa actief is, welke bonnenprinter wordt gebruikt en hoe het kasboek wordt bijgehouden.',
            sections: [
                [
                    'heading' => 'Wat kun je hier instellen?',
                    'body' => <<<MARKDOWN
De kassapagina bundelt drie onderwerpen:

- **POS aan of uit**: de hoofdschakelaar die bepaalt of het kassagedeelte beschikbaar is in het admin paneel.
- **Bonnenprinter**: de printer waarop kassabonnen worden afgedrukt, met dezelfde verbindingsopties als de factuurprinter (CUPS, netwerk of Windows).
- **Kasboek**: of je het contante geld in de lade wilt bijhouden en met welk startbedrag.

De optie "automatisch printen" kun je gebruiken om bonnen meteen uit de printer te laten rollen, zowel voor verkopen via de kassa als voor bestellingen die daarbuiten binnenkomen.
MARKDOWN,
                ],
                [
                    'heading' => 'Wanneer gebruik je het kasboek?',
                    'body' => <<<MARKDOWN
Een kasboek is alleen relevant als je daadwerkelijk contant geld aanneemt in een fysieke winkel. Het kasboek houdt bij hoeveel contant geld er in de lade zit, hoeveel erbij komt door verkopen en hoeveel eruit gaat door bijvoorbeeld wisselgeld of afstortingen.

Werk je alleen met pin en online betalingen? Dan kun je het kasboek uit laten staan.
MARKDOWN,
                ],
            ],
            fields: [
                'POS actief' => 'Hoofdschakelaar voor de kassa-functionaliteit. Uit verbergt het hele kassagedeelte in het admin paneel.',
                'Bonnenprinter verbinding' => 'Verbindingstype voor de bonnenprinter. CUPS voor Linux/Mac, Netwerk voor een printer met eigen IP en Windows voor een gedeelde Windows printer.',
                'Bonnenprinter naam' => 'De naam, het IP adres of de share van de bonnenprinter, afhankelijk van het gekozen verbindingstype.',
                'Kassalade aanwezig' => 'Aan als je een fysieke kassalade hebt waarin contant geld zit. Hiermee komen extra opties zoals het kasboek beschikbaar.',
                'Kasboek bijhouden' => 'Aan zorgt dat alle contante mutaties (verkopen, wisselgeld, afstortingen) automatisch in het kasboek worden bijgehouden.',
                'Startbedrag kassa' => 'Het startbedrag in euro dat aan het begin van de dag in de kassalade zit. Wordt gebruikt als beginsaldo van het kasboek.',
                'Automatisch bon printen (POS)' => 'Aan zorgt dat na elke verkoop via de kassa direct een bon wordt afgedrukt. Uit laat je de bon handmatig printen of overslaan.',
                'Automatisch bon printen (andere bestellingen)' => 'Aan print ook automatisch een bon voor bestellingen die niet via de kassa binnenkomen, bijvoorbeeld online bestellingen die je in de winkel verwerkt.',
            ],
            tips: [
                'Test de bonnenprinter eerst met een proefverkoop voordat je live gaat in de winkel.',
                'Begin elke werkdag met het juiste startbedrag in de kassalade om verschillen in het kasboek te voorkomen.',
            ],
        );

        cms()->registerSettingsDocs(
            page: \Dashed\DashedEcommerceCore\Filament\Pages\Settings\ProductSettingsPage::class,
            title: '',
            intro: 'Bepaal hoe producten worden getoond, gesorteerd en gefilterd in je webshop. Ook welke pagina dient als winkelwagen, checkout of orderoverzicht stel je hier in. Per site configureerbaar.',
            sections: [
                [
                    'heading' => 'Wat kun je hier instellen?',
                    'body' => <<<MARKDOWN
De pagina is opgedeeld in een paar groepen:

- **Toevoegen aan winkelwagen**: bepaal wat er gebeurt nadat een klant op "in winkelwagen" klikt.
- **Sortering en filters**: kies de standaard sortering op productoverzichten en hoe filterwaarden worden geordend.
- **Variaties**: stel in hoe productvariaties (zoals maat en kleur) zich gedragen.
- **Pagina koppelingen**: koppel de juiste content pagina aan functies zoals winkelwagen, checkout en orderbevestiging.
- **Voorraad en categorieen**: extra opties voor voorraadweergave en categoriepagina\'s.
MARKDOWN,
                ],
            ],
            fields: [
                'Redirect na in winkelwagen' => 'Wat er gebeurt na het toevoegen aan de winkelwagen. `same` blijft op dezelfde pagina, `cart` stuurt naar de winkelwagen en `checkout` direct naar afrekenen.',
                'Sortering filter opties' => 'Volgorde van filterwaarden in de zijbalk. `order` gebruikt de handmatig ingestelde volgorde, `name` sorteert alfabetisch.',
                'Datum in toekomst verplicht' => 'Bepaalt of de "weer op voorraad vanaf" datum in de toekomst moet liggen om getoond te worden. Voorkomt dat verlopen data per ongeluk zichtbaar blijven.',
                'Standaard sortering producten' => 'Standaard sortering op productoverzichten: prijs, aantal verkopen, voorraad, aanmaakdatum, handmatige volgorde of via productgroepen.',
                'Richting productsortering' => 'Richting van de standaardsortering. DESC is van hoog naar laag (of nieuw naar oud), ASC is van laag naar hoog.',
                'Standaard sortering categorieen' => 'Standaard sortering binnen een categoriepagina. Kan afwijken van de algemene productoverzichten.',
                'Richting categoriesortering' => 'Richting van de sortering binnen categoriepagina\'s.',
                'Producten per pagina' => 'Aantal producten dat per pagina wordt getoond op overzichten. Gangbare waardes liggen tussen 12 en 48.',
                'Producten overzicht pagina' => 'Welke pagina dient als algemeen productoverzicht. Wordt gebruikt voor links naar "alle producten".',
                'Winkelwagen pagina' => 'De pagina die als winkelwagen fungeert. Hier komt de klant terecht na het toevoegen van een product (afhankelijk van de redirect instelling).',
                'Afreken pagina' => 'De pagina waar de klant afrekent.',
                'Bestellingen overzicht pagina' => 'De pagina met het overzicht van eerdere bestellingen van de ingelogde klant.',
                'Bestelling detail pagina' => 'De pagina die een individuele bestelling toont, zoals de orderbevestiging direct na betaling.',
                'Livewire variatie stijl' => 'Aan gebruikt een eenvoudige Livewire variant van de variatieselector. Let op: dit vereist een specifieke implementatie in je theme. Laat uit als je niet zeker weet of je theme dit ondersteunt.',
                'Redirect bij variatie wijzig' => 'Aan stuurt de klant automatisch naar de juiste URL zodra hij een andere variatie kiest, zodat de URL altijd het juiste product weergeeft.',
                'Categorie index actief' => 'Aan zorgt dat er een overzichtspagina van alle categorieen beschikbaar is, met links naar de losse categoriepagina\'s.',
                'Groep vullen met eerste product' => 'Aan vult een productgroep automatisch met het eerste onderliggende product als de groep zelf wordt geopend, zodat de klant niet eerst een variant hoeft te kiezen.',
                'Bundel product afbeeldingen' => 'Aan toont op een bundelproduct ook de afbeeldingen van de losse producten in de bundel, in plaats van alleen de bundel-afbeelding zelf.',
            ],
            tips: [
                'De juiste pagina koppelingen zijn cruciaal: een verkeerde verwijzing leidt tot 404 fouten in de checkout flow.',
                'Test de standaard sortering met een paar voorbeeldproducten om te zien hoe het er voor klanten uitziet.',
            ],
        );

        cms()->registerSettingsDocs(
            page: \Dashed\DashedEcommerceCore\Filament\Pages\Settings\VATSettingsPage::class,
            title: '',
            intro: 'Bepaal of prijzen in de webshop inclusief of exclusief BTW worden getoond en berekend. Per site instelbaar, zodat je voor B2C en B2B sites een eigen keuze kunt maken.',
            sections: [
                [
                    'heading' => 'Wat kun je hier instellen?',
                    'body' => <<<MARKDOWN
Met deze instelling kies je per site hoe prijzen worden verwerkt:

- **Inclusief BTW**: de getoonde prijs bevat al de belasting. Tijdens het afrekenen wordt de BTW uit de prijs gehaald en apart vermeld.
- **Exclusief BTW**: de getoonde prijs is zonder belasting. BTW wordt tijdens het afrekenen bovenop de prijs berekend.

De meeste consumentenwebshops tonen prijzen inclusief BTW. B2B webshops tonen vaak exclusief BTW.
MARKDOWN,
                ],
                [
                    'heading' => 'Wanneer gebruik je dit?',
                    'body' => <<<MARKDOWN
Bepaal de keuze aan de hand van je doelgroep:

- Verkoop je vooral aan particulieren? Kies dan inclusief BTW. Dat is in Nederland verplicht voor consumentenprijzen.
- Verkoop je vooral aan zakelijke klanten met een geldig BTW nummer? Dan is exclusief BTW gebruikelijker, omdat de klant de BTW toch terugvordert.

Verander deze instelling niet halverwege een lopende periode. Dat veroorzaakt inconsistentie in historische bestellingen en kan tot vervelende vragen van de boekhouder leiden.
MARKDOWN,
                ],
            ],
            fields: [
                'Prijzen inclusief BTW' => 'Aan betekent dat alle prijzen in de webshop inclusief BTW zijn. Uit betekent dat BTW bovenop de prijs komt bij het afrekenen. Verander deze instelling niet halverwege een lopende periode, dat veroorzaakt inconsistentie in historische bestellingen.',
            ],
            tips: [
                'Voor een Nederlandse consumentenwebshop is inclusief BTW vrijwel altijd de juiste keuze.',
                'Overleg bij twijfel met je boekhouder voordat je de instelling aanpast.',
            ],
        );

        cms()->registerResourceDocs(
            resource: \Dashed\DashedEcommerceCore\Filament\Resources\ProductExtraResource::class,
            title: 'Product extras',
            intro: 'Beheer herbruikbare extra opties die je aan producten kunt koppelen, zoals een geschenkverpakking, spoedlevering of verlengde garantie. Maak een extra een keer aan en koppel hem daarna aan zoveel producten, groepen of categorieen als je wilt.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- Nieuwe extras aanmaken met een naam, prijs en eventuele afbeelding.
- Bepalen of een extra verplicht of optioneel is voor de klant.
- Meerdere waardes toevoegen binnen een extra (bijvoorbeeld verschillende verpakkingsformaten).
- Bestaande extras aanpassen, verbergen of verwijderen.
- De volgorde aanpassen door rijen te verslepen.
MARKDOWN,
                ],
                [
                    'heading' => 'Koppelen aan producten',
                    'body' => 'Een extra doet pas iets wanneer hij ergens aan hangt. Open de extra en kies aan welke producten, productgroepen of categorieen je hem wilt koppelen. Voor grote catalogi zijn er bulk acties: koppel in een klik aan alle producten, alle groepen of alle categorieen tegelijk.',
                ],
            ],
            tips: [
                'Gebruik duidelijke, korte namen. Klanten lezen de extras op de productpagina snel door.',
                'Zet een extra alleen op verplicht als het echt noodzakelijk is. Verplichte extras kunnen anders de conversie drukken.',
            ],
        );

        cms()->registerResourceDocs(
            resource: \Dashed\DashedEcommerceCore\Filament\Resources\ProductFaqResource::class,
            title: 'Product FAQs',
            intro: 'Stel sets met veelgestelde vragen samen die je aan producten of categorieen koppelt. Zo krijgen klanten direct op de productpagina antwoord op vragen die anders tot twijfel of een supportticket leiden.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- Een nieuwe set aanmaken met een eigen titel.
- Zoveel vraag-antwoord paren toevoegen als nodig binnen een set.
- De antwoorden opmaken met koppen, vet, lijstjes en links via de rich editor.
- Bestaande sets aanpassen of verwijderen.
- De volgorde van vragen binnen een set bepalen door ze te verslepen.
MARKDOWN,
                ],
                [
                    'heading' => 'FAQ sets koppelen',
                    'body' => 'Een FAQ set wordt pas getoond als je hem koppelt. Open een product of categorie en kies welke FAQ sets daar getoond moeten worden. Hang je een set aan een categorie, dan erven alle producten in die categorie diezelfde vragen automatisch.',
                ],
            ],
            tips: [
                'Maak generieke sets zoals "Verzending", "Retour" en "Onderhoud" zodat je ze op veel producten kunt hergebruiken.',
                'Houd antwoorden kort. Verwijs voor details naar een aparte pagina.',
            ],
        );

        cms()->registerResourceDocs(
            resource: \Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterOptionResource::class,
            title: 'Product filter opties',
            intro: 'Individuele waardes binnen een filter, zoals "Rood" en "Blauw" bij het filter Kleur, of "S", "M" en "L" bij Maat. De opties die hier bestaan verschijnen als aanvinkbare keuzes in de filterbalk op de productoverzichten.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => 'Filter opties beheer je meestal direct vanuit het bijbehorende filter, niet vanuit deze losse lijst. Vanuit de filter zelf voeg je nieuwe opties toe, bepaal je de volgorde en vul je extra velden in afhankelijk van het filtertype.',
                ],
                [
                    'heading' => 'Type-afhankelijke velden',
                    'body' => 'Welke velden je per optie invult hangt af van het type filter waarin de optie zit. Bij een gewoon tekst- of knopfilter is een naam genoeg. Bij een afbeeldingsfilter verschijnt automatisch een upload veld zodat je bijvoorbeeld een kleurstaal of materiaalfoto kunt toevoegen.',
                ],
            ],
            tips: [
                'Kies korte, duidelijke namen. "Rood" werkt beter dan "Rood gemeleerd uniseks".',
                'Zet vergelijkbare opties in een logische volgorde, bijvoorbeeld maten van klein naar groot.',
            ],
        );

        cms()->registerResourceDocs(
            resource: \Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterResource::class,
            title: 'Product filters',
            intro: 'Definieer de filters die bezoekers op de productoverzichten kunnen gebruiken, zoals Kleur, Maat, Merk of Opslagcapaciteit. Per filter bepaal je hoe het eruitziet en welke opties erin komen.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- Nieuwe filters aanmaken met een naam die klanten zien.
- Het weergavetype kiezen: een dropdown, knoppen, kleurstalen of afbeeldingen.
- Bepalen of voorraad meegenomen wordt, zodat opties waarvoor niks op voorraad is automatisch verdwijnen.
- Bestaande filters aanpassen of verwijderen.
- De volgorde aanpassen zodat de voor jou belangrijkste filters bovenaan staan.
MARKDOWN,
                ],
                [
                    'heading' => 'Opties per filter beheren',
                    'body' => 'Elk filter heeft zijn eigen lijst met opties, te beheren via het tabblad aan de rechterkant van een filter. Daar voeg je de individuele waardes toe, zoals de verschillende kleuren bij een filter Kleur of maten bij een filter Maat.',
                ],
            ],
            tips: [
                'Kies per filter bewust een type. Knoppen werken goed voor maten, kleurstalen voor kleuren, een dropdown voor lange lijsten.',
                'Houd het aantal filters overzichtelijk. Te veel filters leidt klanten af in plaats van te helpen.',
            ],
        );

        cms()->registerResourceDocs(
            resource: \Dashed\DashedEcommerceCore\Filament\Resources\ProductGroupResource::class,
            title: 'Product groepen',
            intro: 'Een productgroep bundelt variaties van hetzelfde product, bijvoorbeeld een shirt in meerdere maten en kleuren. De groep deelt de algemene informatie, categorieen, filters en kenmerken, terwijl elke variatie zijn eigen prijs, voorraad en SKU heeft.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- Een nieuwe groep aanmaken met naam, beschrijving, categorieen en afbeeldingen.
- Kenmerken en filters instellen die voor alle variaties gelden.
- Variaties aan de groep koppelen of nieuwe aanmaken.
- Content toevoegen zoals rich text, FAQ sets en product tabs.
- Groepen aanpassen, dupliceren of verwijderen.
MARKDOWN,
                ],
                [
                    'heading' => 'Variaties automatisch aanmaken',
                    'body' => 'Bovenaan een groep staat de actie "Ontbrekende variaties aanmaken". Die bekijkt de filters die je op de groep hebt gezet (zoals Maat en Kleur) en maakt in een keer alle combinaties aan die nog niet bestaan. Een badge laat zien hoeveel combinaties er ontbreken.',
                ],
                [
                    'heading' => 'AI beschrijvingen',
                    'body' => 'Binnen een groep vind je een AI knop waarmee je een productbeschrijving automatisch laat genereren op basis van de titel, kenmerken en filters. Het resultaat kun je daarna vrij bewerken voordat je opslaat.',
                ],
            ],
            tips: [
                'Zet eerst de filters goed, dan pas variaties aanmaken. Zo klopt de combinatiematrix meteen.',
                'Houd beschrijving, FAQ en tabs op de groep. Dan blijft alles consistent tussen de variaties.',
                'Controleer AI gegenereerde teksten altijd op toon, feiten en doelgroep voordat je ze publiceert.',
            ],
        );

        cms()->registerResourceDocs(
            resource: \Dashed\DashedEcommerceCore\Filament\Resources\ProductResource::class,
            title: 'Producten',
            intro: 'Het hart van je catalogus. Hier beheer je alle producten van je webshop, zowel zelfstandige items als variaties binnen een productgroep. Per product stel je prijs, voorraad, filters, kenmerken en content in.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- Nieuwe producten aanmaken of bestaande bewerken.
- Prijs, kortingsprijs, SKU, barcode en voorraad beheren.
- Producten koppelen aan categorieen en filters.
- Afbeeldingen, beschrijvingen, FAQ en tabs toevoegen.
- Bundelproducten samenstellen uit meerdere losse producten.
- Producten zichtbaar of onzichtbaar maken in de winkel.
MARKDOWN,
                ],
                [
                    'heading' => 'Tabbladen in een product',
                    'body' => <<<MARKDOWN
Binnen een product zijn de instellingen verdeeld over tabbladen zodat je snel bij het juiste onderdeel bent:

- Algemeen: titel, beschrijving, afbeeldingen en categorieen.
- Voorraad: aantallen, waarschuwingen en backorder instellingen.
- Prijs en SKU: verkoopprijs, aanbiedingsprijs, kostprijs en codes.
- Filters: koppelingen met filter opties zoals kleur en maat.
- Kenmerken: technische specificaties en eigenschappen.
- Content: rich tekst, FAQ sets en product tabs.
MARKDOWN,
                ],
                [
                    'heading' => 'Snelle bewerkingen',
                    'body' => 'In de productlijst staat een snelle actie knop waarmee je prijs en voorraad direct in een klein pop-up venster kunt aanpassen, zonder het hele product te hoeven openen. Ideaal voor dagelijkse prijs- en voorraadupdates.',
                ],
            ],
            tips: [
                'Gebruik de snelle acties voor dagelijkse prijs- en voorraadupdates. Dat scheelt enorm veel klikken.',
                'Vul altijd duidelijke SKU codes in. Dat maakt bestellingen, pickprocessen en integraties met boekhouding veel makkelijker.',
                'Werk zoveel mogelijk met productgroepen bij producten met variaties, in plaats van losse producten per maat of kleur.',
                'Controleer AI teksten altijd voordat je publiceert.',
            ],
        );

        cms()->registerResourceDocs(
            resource: \Dashed\DashedEcommerceCore\Filament\Resources\ProductTabResource::class,
            title: 'Product tabs',
            intro: 'Herbruikbare content tabs die op productpagina\'s verschijnen, zoals "Specificaties", "Onderhoud" of "In de doos". Maak een tab een keer aan en koppel hem aan zoveel producten of categorieen als je wilt.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- Nieuwe tabs aanmaken met een titel en rijke inhoud.
- Inhoud opmaken met koppen, lijstjes, tabellen, links en afbeeldingen via de rich editor.
- Bestaande tabs aanpassen zodat de wijziging direct op alle gekoppelde producten doorwerkt.
- Tabs verwijderen wanneer ze niet meer gebruikt worden.
MARKDOWN,
                ],
                [
                    'heading' => 'Koppelen aan producten',
                    'body' => 'Een tab is pas zichtbaar als hij ergens gekoppeld is. Open de tab en kies aan welke producten of categorieen hij moet verschijnen. Is hij aan een categorie gekoppeld, dan erven alle producten in die categorie de tab automatisch.',
                ],
            ],
            tips: [
                'Maak generieke tabs zoals "Onderhoud", "Garantie" en "Specificaties" die je op veel producten kunt hergebruiken.',
                'Gebruik tabellen in de rich editor voor specificaties.',
                'Koppel tabs bij voorkeur aan categorieen in plaats van losse producten.',
            ],
        );

        cms()->registerResourceDocs(
            resource: \Dashed\DashedEcommerceCore\Filament\Resources\ShippingClassResource::class,
            title: 'Verzendklassen',
            intro: 'Met verzendklassen reken je aanvullende verzendkosten voor producten die meer dan gemiddeld kosten om te versturen, bijvoorbeeld op basis van afmeting of gewicht. Per klasse stel je per verzendzone een eigen toeslag in.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- Nieuwe klassen aanmaken met een eigen naam.
- Per actieve verzendzone een prijs instellen die bovenop de standaard verzendkosten komt.
- Bestaande klassen aanpassen of verwijderen.
- Klassen beschikbaar maken zodat je ze op producten kunt selecteren.
MARKDOWN,
                ],
                [
                    'heading' => 'Hoe klassen werken',
                    'body' => 'Wanneer je een verzendklasse aanmaakt verschijnen automatisch velden voor elke verzendzone die in je webshop bestaat. Tijdens de checkout pakt de webshop de klasse van elk product in de winkelwagen en telt de toeslag op bij de gekozen verzendmethode.',
                ],
            ],
            tips: [
                'Houd het aantal klassen beperkt. Drie of vier duidelijke categorieen werkt beter dan tien nauwelijks verschillende.',
                'Controleer je toeslagen regelmatig tegen de werkelijke verzendprijzen van je vervoerder.',
            ],
        );

        cms()->registerResourceDocs(
            resource: \Dashed\DashedEcommerceCore\Filament\Resources\ShippingMethodResource::class,
            title: 'Verzendmethodes',
            intro: 'Verzendmethodes zijn de opties die klanten tijdens de checkout zien, zoals "Standaard verzending", "Express", "Afhalen in de winkel" of "Gratis vanaf 75 euro". Per methode bepaal je de kosten en onder welke voorwaarden hij getoond wordt.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- Een naam en beschrijving invullen zoals klanten ze in de checkout zien.
- Kosten instellen: vast bedrag, variabel op basis van order of gewicht, of gratis vanaf een drempel.
- Minimum en maximum order waardes instellen om de methode alleen te tonen in de juiste range.
- Bepaalde producten uitsluiten waarbij de methode niet gebruikt mag worden.
- De methode actief of inactief maken.
MARKDOWN,
                ],
                [
                    'heading' => 'Waar het vandaan komt',
                    'body' => 'Verzendmethodes horen altijd bij een verzendzone. Je beheert ze daarom vanuit de zone zelf, waar ze onder elkaar gegroepeerd staan.',
                ],
            ],
            tips: [
                'Gebruik duidelijke namen die de klant meteen begrijpt. "Levering 1-2 werkdagen" werkt beter dan "Standaard".',
                'Zet een drempel voor gratis verzending, dat verhoogt vaak het gemiddelde orderbedrag.',
                'Sluit grote of zware producten uit bij bezorgopties die ze niet aankunnen.',
            ],
        );

        cms()->registerResourceDocs(
            resource: \Dashed\DashedEcommerceCore\Filament\Resources\ShippingZoneResource::class,
            title: 'Verzendzones',
            intro: 'Verzendzones verdelen de wereld in gebieden waar je op een eigen manier wilt verzenden en factureren. Per zone kies je welke landen erbij horen, welke BTW regels gelden, welke betaalmethodes uitgesloten zijn en welke verzendmethodes beschikbaar zijn.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- Nieuwe zones aanmaken, bijvoorbeeld Nederland, Belgie, Europa en Rest van de wereld.
- Per zone de landen selecteren die erbij horen.
- BTW regels instellen die voor de landen in de zone gelden.
- Betaalmethodes verbieden die in die regio niet gebruikt moeten worden.
- Verzendmethodes toevoegen die bij de zone horen.
MARKDOWN,
                ],
                [
                    'heading' => 'OSS en BTW per land',
                    'body' => 'Voor verkopen binnen de EU ondersteunen verzendzones de One-Stop Shop regeling. Daarmee reken je het BTW tarief van het land van de koper in plaats van je eigen BTW tarief, zodra je boven de Europese drempel uitkomt. Per land in de zone kun je het juiste tarief instellen.',
                ],
            ],
            tips: [
                'Begin met een zone voor je thuisland en een voor de rest van je leveringsgebied.',
                'Zet OSS BTW tarieven goed zodra je actief in meerdere EU landen verkoopt.',
                'Verbied betaalmethodes per zone als bepaalde opties in een regio niet werken, zoals iDEAL buiten Nederland.',
            ],
        );

        cms()->registerSettingsDocs(
            page: \Dashed\DashedEcommerceCore\Filament\Pages\Statistics\ActionsStatisticsPage::class,
            title: 'Winkelwagen acties statistieken',
            intro: 'Op deze pagina zie je hoe vaak klanten producten in hun winkelwagen leggen en er weer uithalen. Zo krijg je inzicht in twijfelmomenten tijdens het winkelen.',
            sections: [
                [
                    'heading' => 'Wat zie je hier?',
                    'body' => <<<MARKDOWN
- Een lijngrafiek die het aantal toegevoegde producten vergelijkt met het aantal verwijderde producten over de gekozen periode.
- Statistiek-kaarten met de totale aantallen en gemiddelden per dag.
- Een tabel met de producten die het vaakst worden toegevoegd aan de winkelwagen.
- Een tabel met de producten die het vaakst weer uit de winkelwagen verdwijnen.
MARKDOWN,
                ],
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => 'Kies een datumbereik om de trends over die periode te bekijken. De grafiek en tabellen worden automatisch bijgewerkt zodra je de datums aanpast.',
                ],
            ],
            fields: [
                'Startdatum' => 'Het begin van de periode waarover je de winkelwagen acties wil bekijken.',
                'Einddatum' => 'Het einde van de periode waarover je de winkelwagen acties wil bekijken.',
            ],
            tips: [
                'Als een product vaak wordt toegevoegd maar ook vaak weer verwijderd, kan dat wijzen op onduidelijke prijzen, verzendkosten die laat zichtbaar worden of twijfel over de productinformatie.',
                'Vergelijk een rustige week met een actieweek om te zien of kortingen daadwerkelijk meer producten in de winkelwagen krijgen.',
            ],
        );

        cms()->registerSettingsDocs(
            page: \Dashed\DashedEcommerceCore\Filament\Pages\Statistics\DiscountStatisticsPage::class,
            title: 'Korting statistieken',
            intro: 'Op deze pagina zie je hoe goed je kortingscodes presteren. Zo weet je welke acties omzet opleveren en hoeveel korting je in totaal weggeeft.',
            sections: [
                [
                    'heading' => 'Wat zie je hier?',
                    'body' => <<<MARKDOWN
- Een lijngrafiek met het totale kortingsbedrag per dag binnen de gekozen periode.
- Statistiek-kaarten met het aantal bestellingen met korting, de totale omzet, het totaal aan gegeven korting, de gemiddelde korting per bestelling en het aantal verkochte producten.
- Een tabel met alle bestellingen waarop een kortingscode is gebruikt.
MARKDOWN,
                ],
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => 'Kies eerst een periode. Filter daarna op een specifieke kortingscode als je maar een actie wil bekijken, of laat het leeg om alle codes samen te zien. Met de order status kun je bijvoorbeeld alleen betaalde bestellingen meenemen.',
                ],
            ],
            fields: [
                'Periode' => 'Het datumbereik waarover de kortingen worden berekend.',
                'Kortingscode' => 'Kies een specifieke kortingscode, of laat leeg om alle codes samen te analyseren.',
                'Order status' => 'Bepaal welke bestellingen worden meegeteld, bijvoorbeeld alleen betaalde of alleen verzonden bestellingen.',
            ],
            tips: [
                'Vergelijk de totale omzet van bestellingen met korting met het bedrag aan gegeven korting om te zien of een actie rendabel was.',
                'Een hoge gemiddelde korting per bestelling kan betekenen dat de code vooral door grote klanten wordt gebruikt.',
            ],
        );

        cms()->registerSettingsDocs(
            page: \Dashed\DashedEcommerceCore\Filament\Pages\Statistics\ProductGroupStatisticsPage::class,
            title: 'Product groep statistieken',
            intro: 'Op deze pagina zie je hoe hele productgroepen presteren in plaats van losse producten. Handig om te ontdekken welke categorieen het beste lopen.',
            sections: [
                [
                    'heading' => 'Wat zie je hier?',
                    'body' => <<<MARKDOWN
- Een lijngrafiek met het aantal verkochte producten per dag, opgeteld per groep.
- Statistiek-kaarten met totalen en gemiddelden over de gekozen periode.
- Een tabel met alle productgroepen, gesorteerd op aantal verkochte producten.
MARKDOWN,
                ],
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => 'Kies een periode om de verkopen over die tijd te bekijken. Met de taalfilter kijk je naar een specifieke taalversie van je shop. Gebruik het zoekveld om snel een bepaalde groep terug te vinden.',
                ],
            ],
            fields: [
                'Periode' => 'Het datumbereik waarover de verkopen per groep worden berekend.',
                'Taal' => 'Bekijk de cijfers voor een specifieke taal of voor alle talen samen.',
                'Zoeken op groepsnaam' => 'Filter de tabel snel op een deel van de naam van een productgroep.',
            ],
            tips: [
                'Groepen die achterblijven op de ranglijst verdienen soms extra aandacht op de homepage of in een nieuwsbrief.',
                'Door per taal te filteren zie je of bepaalde groepen beter aanslaan in een specifieke markt.',
            ],
        );

        cms()->registerSettingsDocs(
            page: \Dashed\DashedEcommerceCore\Filament\Pages\Statistics\ProductStatisticsPage::class,
            title: 'Product statistieken',
            intro: 'Op deze pagina zie je hoeveel er per product verkocht wordt. Zo weet je direct welke producten goed lopen en welke wat extra aandacht kunnen gebruiken.',
            sections: [
                [
                    'heading' => 'Wat zie je hier?',
                    'body' => <<<MARKDOWN
- Een lijngrafiek met het aantal verkochte producten per dag binnen de gekozen periode.
- Statistiek-kaarten met totalen en gemiddelden.
- Een tabel met al je producten, gesorteerd op aantal verkochte eenheden.
MARKDOWN,
                ],
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => 'Kies een periode en eventueel een taal om in te zoomen op een specifieke markt. Met het zoekveld vind je snel een bepaald product terug in de tabel.',
                ],
            ],
            fields: [
                'Periode' => 'Het datumbereik waarover de verkopen per product worden berekend.',
                'Taal' => 'Bekijk de cijfers voor een specifieke taal of voor alle talen samen.',
                'Zoeken op productnaam' => 'Filter de tabel snel op een deel van de productnaam.',
            ],
            tips: [
                'Producten onderaan de lijst zijn kandidaten voor een actie, een betere productfoto of een uitgebreidere beschrijving.',
                'Vergelijk twee periodes om te zien of een product na een aanpassing beter of juist slechter verkoopt.',
            ],
        );

        cms()->registerSettingsDocs(
            page: \Dashed\DashedEcommerceCore\Filament\Pages\Statistics\RevenueStatisticsPage::class,
            title: 'Omzet statistieken',
            intro: 'Op deze pagina vind je het meest uitgebreide overzicht van je omzet, kosten en bestellingen. Je kunt op veel manieren filteren om precies de cijfers te zien die je zoekt.',
            sections: [
                [
                    'heading' => 'Wat zie je hier?',
                    'body' => <<<MARKDOWN
- Een lijngrafiek met je omzet over de tijd, instelbaar per uur, dag, week, maand, kwartaal of jaar.
- Statistiek-kaarten met het aantal bestellingen, de totale omzet, betaalkosten, verzendkosten, gegeven kortingen, BTW en het gemiddelde bestelbedrag.
- Uitgebreide filters om in te zoomen op betaalmethode, status, retouren en de herkomst van bestellingen.
MARKDOWN,
                ],
                [
                    'heading' => 'Hoe gebruik je de filters?',
                    'body' => 'Begin met een periode preset zoals deze maand of vorige maand, of vul zelf een start- en einddatum in. Kies daarna de granulariteit waarmee de grafiek wordt getekend. Met de overige filters maak je de selectie steeds specifieker. De grafiek en kaarten worden automatisch bijgewerkt.',
                ],
            ],
            fields: [
                'Periode preset' => 'Snelle keuze tussen deze maand, vorige maand, dit jaar of een eigen datumbereik.',
                'Granulariteit' => 'Hoe gedetailleerd de grafiek is: per uur, dag, week, maand, kwartaal of jaar.',
                'Startdatum' => 'Het begin van de periode als je een eigen datumbereik kiest.',
                'Einddatum' => 'Het einde van de periode als je een eigen datumbereik kiest.',
                'Order status' => 'Beperk de cijfers tot bestellingen met een bepaalde status.',
                'Betaalmethode' => 'Bekijk alleen bestellingen die met een specifieke betaalmethode zijn afgerekend.',
                'Fulfillment status' => 'Filter op de verwerkingsstatus van een bestelling.',
                'Retour status' => 'Laat alleen bestellingen zien die geheel of gedeeltelijk zijn geretourneerd, of sluit retouren juist uit.',
                'Order herkomst' => 'Filter op waar de bestelling vandaan komt, bijvoorbeeld je eigen webshop of een marktplaats.',
            ],
            tips: [
                'Vergelijk maand op maand door eerst de huidige maand te bekijken en daarna de vorige maand te kiezen.',
                'Kies een kortere granulariteit zoals per uur om te zien op welk moment van de dag de meeste bestellingen binnenkomen.',
                'Door op betaalmethode te filteren zie je direct welke methode de meeste omzet oplevert en wat je aan transactiekosten kwijt bent.',
            ],
        );

        cms()->registerSettingsDocs(
            page: \Dashed\DashedEcommerceCore\Filament\Pages\Exports\ExportInvoicesPage::class,
            title: 'Facturen exporteren',
            intro: 'Op deze pagina maak je in een keer een PDF aan met de facturen over een gekozen periode. Je ontvangt het bestand per e-mail zodra de export klaar is.',
            sections: [
                [
                    'heading' => 'Wat zie je hier?',
                    'body' => <<<MARKDOWN
- Een formulier waarin je de periode invult waarover je facturen wilt exporteren.
- Een keuze voor de modus: alle bestellingen samenvoegen op een overzichtsfactuur, of alle losse facturen onder elkaar in een PDF.
- Een knop om de export te starten en een bevestiging dat deze is ingepland.
MARKDOWN,
                ],
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => 'Vul een startdatum en einddatum in, kies de modus en klik op de knop om de export te starten. Grote exports worden op de achtergrond verwerkt en komen automatisch in je inbox terecht.',
                ],
            ],
            fields: [
                'Startdatum' => 'De eerste dag waarvan je de facturen wil meenemen in de export.',
                'Einddatum' => 'De laatste dag waarvan je de facturen wil meenemen in de export.',
                'Modus' => 'Kies of je alle bestellingen op een overzichtsfactuur wil, of dat je alle losse facturen samengevoegd in een PDF wil ontvangen.',
            ],
            tips: [
                'Voor je boekhouding is de losse facturen modus meestal het handigst, omdat elke factuur zijn eigen regels en nummers houdt.',
                'Controleer of het e-mailadres van je gebruikersaccount klopt, want daar wordt de export naartoe gestuurd.',
            ],
        );

        cms()->registerSettingsDocs(
            page: \Dashed\DashedEcommerceCore\Filament\Pages\Exports\ExportOrdersPage::class,
            title: 'Bestellingen exporteren',
            intro: 'Op deze pagina zet je je bestellingen om naar een Excel bestand. Handig voor je boekhouding, voor analyses of om gegevens door te geven aan een collega.',
            sections: [
                [
                    'heading' => 'Wat zie je hier?',
                    'body' => <<<MARKDOWN
- Een formulier met een optionele startdatum en einddatum zodat je zelf bepaalt welke bestellingen er meegaan.
- Een keuze voor het type export: normaal, met een regel per bestelling, of per factuurregel, met een regel per product in de bestelling.
- Een knop om de export te starten.
MARKDOWN,
                ],
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => 'Laat de datums leeg om alle bestellingen mee te nemen, of vul een periode in om het overzicht kleiner te maken. Kies daarna het type dat past bij wat je wil doen en start de export.',
                ],
            ],
            fields: [
                'Startdatum' => 'Optioneel. De eerste dag waarvan bestellingen worden meegenomen. Leeg laten betekent vanaf het begin.',
                'Einddatum' => 'Optioneel. De laatste dag waarvan bestellingen worden meegenomen. Leeg laten betekent tot en met vandaag.',
                'Type' => 'Kies Normaal voor een regel per bestelling, of Per factuurregel voor een regel per besteld product.',
            ],
            tips: [
                'De optie Per factuurregel is ideaal als je wil analyseren welke producten samen in een bestelling worden gekocht.',
                'De normale export is het handigst voor een snel overzicht van omzet en klantgegevens.',
            ],
        );

        cms()->registerSettingsDocs(
            page: \Dashed\DashedEcommerceCore\Filament\Pages\Exports\ExportProductsPage::class,
            title: 'Producten exporteren',
            intro: 'Op deze pagina exporteer je je productcatalogus naar een Excel bestand. Handig om voorraad te controleren of om in bulk prijzen aan te passen en terug te importeren.',
            sections: [
                [
                    'heading' => 'Wat zie je hier?',
                    'body' => <<<MARKDOWN
- Een schakelaar om te kiezen of je alleen openbare producten of ook niet zichtbare producten wil meenemen.
- Een knop om de export direct te starten.
MARKDOWN,
                ],
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => 'Zet de schakelaar aan als je alleen producten wil die zichtbaar zijn in je shop. Laat hem uit om echt alle producten te exporteren, inclusief concepten en verborgen items. Klik daarna op de knop om het bestand aan te maken.',
                ],
            ],
            fields: [
                'Alleen openbare producten' => 'Zet aan om alleen producten mee te nemen die op dit moment zichtbaar zijn in de shop. Laat uit voor de volledige catalogus.',
            ],
            tips: [
                'Gebruik deze export als uitgangspunt voor bulk prijsaanpassingen, pas de kolommen aan in Excel en importeer het bestand daarna terug.',
                'Voor een inventariscontrole is de volledige catalogus vaak nuttiger dan alleen de openbare producten.',
            ],
        );

        cms()->registerSettingsDocs(
            page: \Dashed\DashedEcommerceCore\Filament\Pages\POS\POSPage::class,
            title: 'Kassa',
            intro: 'De kassa voor je fysieke winkel. Zoek producten, voeg ze toe aan de winkelwagen, pas kortingen toe en reken af met de klant.',
            sections: [
                [
                    'heading' => 'Wat zie je hier?',
                    'body' => <<<MARKDOWN
De kassa bestaat uit een paar vaste onderdelen die je tijdens het afrekenen gebruikt:

- **Productzoekbalk** om snel artikelen te vinden op naam, SKU of barcode.
- **Winkelwagen** met alle toegevoegde producten, aantallen en regelprijzen.
- **Kortingen sectie** waar je een kortingscode invoert of een nieuwe code aanmaakt.
- **Klantgegevens formulier** voor naam, adres en contactinformatie.
- **Actieknoppen** om als concept op te slaan, een concept te laden of door te gaan naar de betaling.
- **Fullscreen toggle** waarmee je de rest van het dashboard verbergt.
MARKDOWN,
                ],
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- Producten zoeken en toevoegen aan de winkelwagen.
- Aantallen aanpassen of losse regelprijzen overschrijven.
- Een kortingscode toepassen of ter plekke een nieuwe korting aanmaken.
- Klantgegevens invullen of een bestaande klant ophalen uit de database.
- De bestelling als concept opslaan zodat je er later op terug kunt komen.
- Een eerder opgeslagen concept weer openen en afronden.
- Doorgaan naar betaling om af te rekenen met pin, contant of een andere methode.
MARKDOWN,
                ],
            ],
            tips: [
                'Test de bonnenprinter aan het begin van de dag zodat je tijdens een drukke piek niet voor verrassingen komt te staan.',
                'Gebruik de fullscreen modus tijdens drukke momenten. Zo zie je alleen de kassa en raak je niet afgeleid door andere menu items.',
            ],
        );

        cms()->registerSettingsDocs(
            page: \Dashed\DashedEcommerceCore\Filament\Pages\POS\POSCustomerPage::class,
            title: 'Kassa klant scherm',
            intro: 'Een tweede scherm dat je naast de kassa plaatst zodat de klant tijdens het afrekenen mee kan kijken met de winkelwagen en het totaalbedrag.',
            sections: [
                [
                    'heading' => 'Wat zie je hier?',
                    'body' => 'Deze pagina is bedoeld als klantweergave bij de kassa. Je opent hem op een tweede beeldscherm of tablet dat naar de klant is gericht. De klant ziet de regels uit de winkelwagen en het lopende totaal meelopen terwijl jij afrekent. Let op: deze pagina is op dit moment beperkt geimplementeerd.',
                ],
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => 'Dit scherm is puur bedoeld om te tonen. Je bedient het niet zelf tijdens het afrekenen, je gebruikt de gewone kassa daarvoor. Open deze pagina op het tweede scherm en laat hem daar openstaan.',
                ],
            ],
        );

        $this->app->booted(function () {
            $schedule = app(Schedule::class);
            $schedule->command(CheckPastDuePreorderDatesForProductsWithoutStockCommand::class)
                ->daily();
            $schedule->command(CancelOldOrders::class)
                ->everyFifteenMinutes();
            $schedule->command(UpdateProductInformations::class)
                ->daily()
                ->withoutOverlapping();
            $schedule->command(UpdateExpiredGlobalDiscountCodes::class)
                ->everyFiveMinutes()
                ->withoutOverlapping();
            $schedule->command(ClearOldCarts::class)
                ->hourly()
                ->withoutOverlapping();
            $schedule->command(PruneCartLogs::class)
                ->dailyAt('03:15')
                ->withoutOverlapping();
            $schedule->command(SendAbandonedCartEmails::class)
                ->everyFiveMinutes()
                ->withoutOverlapping();
        });

        //Stats components
        Livewire::component('revenue-chart', RevenueChart::class);
        Livewire::component('revenue-cards', RevenueCards::class);
        Livewire::component('product-chart', ProductChart::class);
        Livewire::component('product-cards', ProductCards::class);
        Livewire::component('product-table', ProductTable::class);
        Livewire::component('product-group-chart', ProductGroupChart::class);
        Livewire::component('product-group-cards', ProductGroupCards::class);
        Livewire::component('product-group-table', ProductGroupTable::class);
        Livewire::component('discount-chart', DiscountChart::class);
        Livewire::component('discount-cards', DiscountCards::class);
        Livewire::component('discount-table', DiscountTable::class);
        Livewire::component('action-statistics-chart', ActionStatisticsChart::class);
        Livewire::component('action-statistics-cards', ActionStatisticsCards::class);
        Livewire::component('action-statistics-table', ActionStatisticsTable::class);

        //Backend components
        Livewire::component('change-order-fulfillment-status', ChangeOrderFulfillmentStatus::class);
        Livewire::component('change-order-retour-status', ChangeOrderRetourStatus::class);
        Livewire::component('add-payment-to-order', AddPaymentToOrder::class);
        Livewire::component('cancel-order', CancelOrder::class);
        Livewire::component('send-order-to-fulfillment-companies', SendOrderToFulfillmentCompanies::class);
        Livewire::component('send-order-confirmation-to-email', SendOrderConfirmationToEmail::class);
        Livewire::component('create-order-log', CreateOrderLog::class);
        Livewire::component('order-shipping-information-list', ShippingInformationList::class);
        Livewire::component('order-payment-information-list', PaymentInformationList::class);
        Livewire::component('order-order-products-list', OrderProductsList::class);
        Livewire::component('order-payments-list', PaymentsList::class);
        Livewire::component('order-logs-list', LogsList::class);
        Livewire::component('order-customer-information-block-list', CustomerInformationBlockList::class);
        Livewire::component('order-attribution-information-list', AttributionInformationList::class);
        Livewire::component('order-view-statusses', ViewStatusses::class);
        Livewire::component('order-create-track-and-trace', CreateTrackAndTrace::class);

        //Frontend components
        Livewire::component('cart.cart', Cart::class);
        Livewire::component('cart.cart-count', CartCount::class);
        Livewire::component('cart.add-to-cart', AddToCart::class);
        Livewire::component('cart.added-to-cart-popup', AddedToCart::class);
        Livewire::component('cart.cart-popup', CartPopup::class);
        Livewire::component('cart.cart-suggestions', \Dashed\DashedEcommerceCore\Livewire\Frontend\Cart\CartSuggestions::class);
        Livewire::component('cart.quick-add-product', \Dashed\DashedEcommerceCore\Livewire\Frontend\Cart\QuickAddProduct::class);
        Livewire::component('checkout.checkout', Checkout::class);
        Livewire::component('categories.show-categories', ShowCategories::class);
        Livewire::component('products.show-products', ShowProducts::class);
        Livewire::component('products.show-product', ShowProduct::class);
        Livewire::component('products.searchbar', Searchbar::class);
        Livewire::component('account.orders', Orders::class);
        Livewire::component('orders.view-order', ViewOrder::class);

        Livewire::component(
            'dashed.dashed-ecommerce-core.filament.resources.abandoned-cart-flow-resource.relation-managers.flow-steps-relation-manager',
            FlowStepsRelationManager::class,
        );
        Livewire::component(
            'dashed.dashed-ecommerce-core.filament.resources.abandoned-cart-flow-resource.widgets.abandoned-cart-flow-stats',
            AbandonedCartFlowStats::class,
        );
        Livewire::component(
            'dashed.dashed-ecommerce-core.filament.resources.order-handled-flow-resource.widgets.order-handled-flow-stats',
            \Dashed\DashedEcommerceCore\Filament\Resources\OrderHandledFlowResource\Widgets\OrderHandledFlowStats::class,
        );
        Livewire::component(
            'dashed.dashed-ecommerce-core.filament.resources.order-handled-flow-resource.widgets.order-handled-flow-enrollments',
            \Dashed\DashedEcommerceCore\Filament\Resources\OrderHandledFlowResource\Widgets\OrderHandledFlowEnrollments::class,
        );
        Livewire::component(
            'dashed.dashed-ecommerce-core.filament.widgets.product.product-open-orders-widget',
            \Dashed\DashedEcommerceCore\Filament\Widgets\Product\ProductOpenOrdersWidget::class,
        );
        Livewire::component(
            'dashed.dashed-ecommerce-core.filament.widgets.product.product-group-open-orders-widget',
            \Dashed\DashedEcommerceCore\Filament\Widgets\Product\ProductGroupOpenOrdersWidget::class,
        );

        //POS components
        Livewire::component('point-of-sale', POSPage::class);
        Livewire::component('customer-point-of-sale', POSCustomerPage::class);

        User::addDynamicRelation('orders', function (User $model) {
            return $model->hasMany(Order::class)
                ->whereIn('status', ['paid', 'waiting_for_confirmation', 'partially_paid'])
                ->orderBy('created_at', 'DESC');
        });

        User::addDynamicRelation('allOrders', function (User $model) {
            return $model->hasMany(Order::class)
                ->orderBy('created_at', 'DESC');
        });

        User::addDynamicRelation('lastOrder', function (User $model) {
            return $model->orders()->latest()->first();
        });

        User::addDynamicRelation('lastOrderFromAllOrders', function (User $model) {
            return $model->allOrders()->latest()->first();
        });

        //        $builderBlockClasses = [];
        //        if (config('dashed-ecommerce-core.registerDefaultBuilderBlocks', true)) {
        //            $builderBlockClasses[] = 'builderBlocks';
        //        }

        $builderBlockClasses[] = 'defaultPageBuilderBlocks';

        cms()->builder('builderBlockClasses', [
            self::class => $builderBlockClasses,
        ]);

        cms()->builder('createDefaultPages', [
            self::class => 'createDefaultPages',
        ]);

        cms()->builder('publishOnUpdate', [
            'dashed-ecommerce-core-config',
            'dashed-ecommerce-core-assets',
        ]);


        cms()->builder('blockDisabledForCache', [
            'orders-block',
            'cart-block',
            'checkout-block',
            'view-order-block',
            'all-products',
        ]);

        cms()->builder('plugins', [
            new DashedEcommerceCorePlugin(),
        ]);

        // Samenvatting-mails: registreer de e-commerce contributors
        // bij de centrale registry uit dashed-core. De builder-API
        // mergt automatisch met eerder geregistreerde contributors.
        cms()->builder('summaryContributors', [
            \Dashed\DashedEcommerceCore\Services\Summary\RevenueSummaryContributor::class,
            \Dashed\DashedEcommerceCore\Services\Summary\AbandonedCartSummaryContributor::class,
            \Dashed\DashedEcommerceCore\Services\Summary\OrderFlowSummaryContributor::class,
            \Dashed\DashedEcommerceCore\Services\Summary\CustomerMatchSummaryContributor::class,
        ]);

        Gate::policy(\Dashed\DashedEcommerceCore\Models\Cart::class, \Dashed\DashedEcommerceCore\Policies\CartPolicy::class);
        Gate::policy(\Dashed\DashedEcommerceCore\Models\DiscountCode::class, \Dashed\DashedEcommerceCore\Policies\DiscountCodePolicy::class);
        Gate::policy(\Dashed\DashedEcommerceCore\Models\FulfillmentCompany::class, \Dashed\DashedEcommerceCore\Policies\FulfillmentCompanyPolicy::class);
        Gate::policy(\Dashed\DashedEcommerceCore\Models\OrderLogTemplate::class, \Dashed\DashedEcommerceCore\Policies\OrderLogTemplatePolicy::class);
        Gate::policy(\Dashed\DashedEcommerceCore\Models\Order::class, \Dashed\DashedEcommerceCore\Policies\OrderPolicy::class);
        Gate::policy(\Dashed\DashedEcommerceCore\Models\PaymentMethod::class, \Dashed\DashedEcommerceCore\Policies\PaymentMethodPolicy::class);
        Gate::policy(\Dashed\DashedEcommerceCore\Models\ProductCategory::class, \Dashed\DashedEcommerceCore\Policies\ProductCategoryPolicy::class);
        Gate::policy(\Dashed\DashedEcommerceCore\Models\ProductCharacteristics::class, \Dashed\DashedEcommerceCore\Policies\ProductCharacteristicsPolicy::class);
        Gate::policy(\Dashed\DashedEcommerceCore\Models\ProductExtra::class, \Dashed\DashedEcommerceCore\Policies\ProductExtraPolicy::class);
        Gate::policy(\Dashed\DashedEcommerceCore\Models\ProductFaq::class, \Dashed\DashedEcommerceCore\Policies\ProductFaqPolicy::class);
        Gate::policy(\Dashed\DashedEcommerceCore\Models\ProductFilterOption::class, \Dashed\DashedEcommerceCore\Policies\ProductFilterOptionPolicy::class);
        Gate::policy(\Dashed\DashedEcommerceCore\Models\ProductFilter::class, \Dashed\DashedEcommerceCore\Policies\ProductFilterPolicy::class);
        Gate::policy(\Dashed\DashedEcommerceCore\Models\ProductGroup::class, \Dashed\DashedEcommerceCore\Policies\ProductGroupPolicy::class);
        Gate::policy(\Dashed\DashedEcommerceCore\Models\Product::class, \Dashed\DashedEcommerceCore\Policies\ProductPolicy::class);
        Gate::policy(\Dashed\DashedEcommerceCore\Models\ProductTab::class, \Dashed\DashedEcommerceCore\Policies\ProductTabPolicy::class);
        Gate::policy(\Dashed\DashedEcommerceCore\Models\ShippingClass::class, \Dashed\DashedEcommerceCore\Policies\ShippingClassPolicy::class);
        Gate::policy(\Dashed\DashedEcommerceCore\Models\ShippingMethod::class, \Dashed\DashedEcommerceCore\Policies\ShippingMethodPolicy::class);
        Gate::policy(\Dashed\DashedEcommerceCore\Models\ShippingZone::class, \Dashed\DashedEcommerceCore\Policies\ShippingZonePolicy::class);

        cms()->registerRolePermissions('E-commerce', [
            'view_order' => 'Bestellingen bekijken',
            'edit_order' => 'Bestellingen bewerken',
            'delete_order' => 'Bestellingen verwijderen',
            'view_cart' => 'Winkelwagens bekijken',
            'edit_cart' => 'Winkelwagens bewerken',
            'delete_cart' => 'Winkelwagens verwijderen',
            'view_product' => 'Producten bekijken',
            'edit_product' => 'Producten bewerken',
            'delete_product' => 'Producten verwijderen',
            'view_product_category' => 'Productcategorieën bekijken',
            'edit_product_category' => 'Productcategorieën bewerken',
            'delete_product_category' => 'Productcategorieën verwijderen',
            'view_product_characteristics' => 'Productkenmerken bekijken',
            'edit_product_characteristics' => 'Productkenmerken bewerken',
            'delete_product_characteristics' => 'Productkenmerken verwijderen',
            'view_product_extra' => 'Productextras bekijken',
            'edit_product_extra' => 'Productextras bewerken',
            'delete_product_extra' => 'Productextras verwijderen',
            'view_product_faq' => 'Product FAQ bekijken',
            'edit_product_faq' => 'Product FAQ bewerken',
            'delete_product_faq' => 'Product FAQ verwijderen',
            'view_product_filter' => 'Productfilters bekijken',
            'edit_product_filter' => 'Productfilters bewerken',
            'delete_product_filter' => 'Productfilters verwijderen',
            'view_product_filter_option' => 'Productfilteropties bekijken',
            'edit_product_filter_option' => 'Productfilteropties bewerken',
            'delete_product_filter_option' => 'Productfilteropties verwijderen',
            'view_product_group' => 'Productgroepen bekijken',
            'edit_product_group' => 'Productgroepen bewerken',
            'delete_product_group' => 'Productgroepen verwijderen',
            'view_product_tab' => 'Producttabs bekijken',
            'edit_product_tab' => 'Producttabs bewerken',
            'delete_product_tab' => 'Producttabs verwijderen',
            'view_discount_code' => 'Kortingscodes bekijken',
            'edit_discount_code' => 'Kortingscodes bewerken',
            'delete_discount_code' => 'Kortingscodes verwijderen',
            'view_order_log_template' => 'Orderlog templates bekijken',
            'edit_order_log_template' => 'Orderlog templates bewerken',
            'delete_order_log_template' => 'Orderlog templates verwijderen',
            'view_pos' => 'Point of Sale bekijken',
        ]);

        cms()->registerRolePermissions('Verzending', [
            'view_fulfillment_company' => 'Fulfillment bedrijven bekijken',
            'edit_fulfillment_company' => 'Fulfillment bedrijven bewerken',
            'delete_fulfillment_company' => 'Fulfillment bedrijven verwijderen',
            'view_shipping_class' => 'Verzendklassen bekijken',
            'edit_shipping_class' => 'Verzendklassen bewerken',
            'delete_shipping_class' => 'Verzendklassen verwijderen',
            'view_shipping_method' => 'Verzendmethoden bekijken',
            'edit_shipping_method' => 'Verzendmethoden bewerken',
            'delete_shipping_method' => 'Verzendmethoden verwijderen',
            'view_shipping_zone' => 'Verzendzones bekijken',
            'edit_shipping_zone' => 'Verzendzones bewerken',
            'delete_shipping_zone' => 'Verzendzones verwijderen',
        ]);

        cms()->registerRolePermissions('Betalingen', [
            'view_payment_method' => 'Betaalmethoden bekijken',
            'edit_payment_method' => 'Betaalmethoden bewerken',
            'delete_payment_method' => 'Betaalmethoden verwijderen',
        ]);

        cms()->registerRolePermissions('Statistics', [
            'view_statistics' => 'Statistieken bekijken',
        ]);

        cms()->registerRolePermissions('Export', [
            'view_exports' => 'Exports bekijken',
        ]);
    }

    public static function builderBlocks()
    {
        $defaultBlocks = [
            Block::make('all-products')
                ->label('Alle producten')
                ->schema([]),
            Block::make('few-products')
                ->label('Paar producten')
                ->schema([
                    AppServiceProvider::getDefaultBlockFields(),
                    TextInput::make('title')
                        ->label('Titel'),
                    TextInput::make('subtitle')
                        ->label('Subtitel'),
                    TextInput::make('amount_of_products')
                        ->label('Aantal producten')
                        ->integer()
                        ->required()
                        ->default(4)
                        ->minValue(1)
                        ->maxValue(100),
                    Toggle::make('useCartRelatedItems')
                        ->label('Gebruik gerelateerde producten uit winkelwagen om de lijst aan te vullen'),
                    Select::make('products')
                        ->label('Producten')
                        ->helperText('Leeg laten om automatisch aan te vullen, indien je iets invult, worden alleen de ingevulde getoond')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(function () {
                            $options = [];

                            foreach (Product::publicShowable()->get() as $product) {
                                $options[$product->id] = $product->nameWithParents;
                            }

                            return $options;
                        }),
                ]),
            Block::make('categories')
                ->label('Categorieeen')
                ->schema([
                    AppServiceProvider::getDefaultBlockFields(),
                    TextInput::make('title')
                        ->label('Titel')
                        ->required(),
                    Select::make('categories')
                        ->label('Categorieën')
                        ->searchable()
                        ->preload()
                        ->multiple()
                        ->options(function () {
                            return ProductCategory::all()->mapWithKeys(function ($category) {
                                return [$category->id => $category->nameWithParents];
                            });
                        }),
                ]),
        ];

        cms()
            ->builder('blocks', $defaultBlocks);
    }

    public static function defaultPageBuilderBlocks()
    {
        $defaultBlocks = [
            Block::make('orders-block')
                ->label('Bestellingen')
                ->schema([]),
            Block::make('cart-block')
                ->label('Winkelwagen')
                ->schema([]),
            Block::make('checkout-block')
                ->label('Checkout')
                ->schema([]),
            Block::make('view-order-block')
                ->label('Bestelling')
                ->schema([]),
        ];

        cms()
            ->builder('blocks', $defaultBlocks);
    }

    public function configurePackage(Package $package): void
    {

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        //        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'dashed-ecommerce-core');

        //        $this->publishes([
        //            __DIR__ . '/../resources/views/frontend' => resource_path('views/vendor/dashed-ecommerce-core/frontend'),
        //            __DIR__ . '/../resources/views/emails' => resource_path('views/vendor/dashed-ecommerce-core/emails'),
        //        ], 'dashed-ecommerce-core-views');

        $this->publishes([
            __DIR__ . '/../resources/templates' => resource_path('views/' . config('dashed-core.site_theme', 'dashed')),
            __DIR__ . '/../resources/component-templates' => resource_path('views/components'),
        ], 'dashed-templates');

        cms()->builder(
            'frontendMiddlewares',
            [
                EcommerceFrontendMiddleware::class,
                CaptureAttributionMiddleware::class,
            ]
        );

        cms()->registerRouteModel(Product::class, 'Product');
        cms()->registerRouteModel(ProductGroup::class, 'Product groep');
        cms()->registerRouteModel(ProductCategory::class, 'Product categorie');

        cms()->registerSettingsPage(DefaultEcommerceSettingsPage::class, 'Algemene Ecommerce', 'banknotes', 'Algemene Ecommerce instellingen');
        cms()->registerSettingsPage(InvoiceSettingsPage::class, 'Facturatie instellingen', 'document-check', 'Instellingen voor de facturatie');
        cms()->registerSettingsPage(OrderSettingsPage::class, 'Bestellingen', 'banknotes', 'Instellingen voor de bestellingen');
        cms()->registerSettingsPage(OrderLogTemplateResource::class, 'Bestel log templates', 'newspaper', 'Stel templates in voor bestel logs');
        cms()->registerSettingsPage(PaymentMethodResource::class, 'Betaalmethodes', 'credit-card', 'Stel handmatige betaalmethodes in');
        cms()->registerSettingsPage(VATSettingsPage::class, 'BTW instellingen', 'receipt-percent', 'Beheren hoe je winkel belastingen in rekening brengt');
        cms()->registerSettingsPage(OrderCancelSettingsPage::class, 'Annuleer bestelling instellingen', 'arrow-uturn-left', 'Beheer instellingen voor het annuleren van bestellingen');
        cms()->registerSettingsPage(ProductSettingsPage::class, 'Product instellingen', 'shopping-bag', 'Beheren instellingen over je producten');
        cms()->registerSettingsPage(CheckoutSettingsPage::class, 'Afreken instellingen', 'shopping-cart', 'Je online betaalprocess aanpassen');
        cms()->registerSettingsPage(ShippingClassResource::class, 'Verzendklasses', 'truck', 'Is een product breekbaar of veel groter? Reken een meerprijs');
        cms()->registerSettingsPage(ShippingZoneResource::class, 'Verzendzones', 'truck', 'Bepaal waar je allemaal naartoe verstuurd');
        cms()->registerSettingsPage(ShippingMethodResource::class, 'Verzendmethodes', 'truck', 'Maak verzendmethodes aan');
        cms()->registerSettingsPage(POSSettingsPage::class, 'Point of Sale', 'banknotes', 'Bewerk je POS');
        cms()->registerSettingsPage(CustomerMatchSettingsPage::class, 'Google Ads Customer Match', 'megaphone', 'HTTPS-feed met gehashte klantdata voor Google Ads Customer Match');

        $package
            ->name('dashed-ecommerce-core')
            ->hasRoutes([
                'frontend',
                'point-of-sale',
                'google-ads',
                'order-handled-frontend',
            ])
            ->hasConfigFile([
                'dashed-ecommerce-core',
                'dompdf',
            ])
            ->hasViews()
            ->hasCommands([
                CheckPastDuePreorderDatesForProductsWithoutStockCommand::class,
                RecalculatePurchasesCommand::class,
                CancelOldOrders::class,
                SendInvoices::class,
                UpdateProductInformations::class,
                MigrateToV3::class,
                UpdateExpiredGlobalDiscountCodes::class,
                ClearOldCarts::class,
                SendAbandonedCartEmails::class,
                PruneCartLogs::class,
                BackfillOrderFlowEnrollmentReviewUrls::class,
            ]);

    }

    public static function createDefaultPages(): void
    {
        if (! \Dashed\DashedCore\Models\Customsetting::get('product_overview_page_id')) {
            $page = new \Dashed\DashedPages\Models\Page();
            foreach (Locales::getActivatedLocalesFromSites() as $locale) {
                $page->setTranslation('name', $locale, 'Producten');
                $page->setTranslation('slug', $locale, 'producten');
                $page->setTranslation('content', $locale, [
                    [
                        'data' => [
                            'in_container' => true,
                            'top_margin' => true,
                            'bottom_margin' => true,
                        ],
                        'type' => 'all-products',
                    ],
                ]);
            }
            $page->save();

            \Dashed\DashedCore\Models\Customsetting::set('product_overview_page_id', $page->id);
        }

        if (! \Dashed\DashedCore\Models\Customsetting::get('orders_page_id')) {
            $page = new \Dashed\DashedPages\Models\Page();
            foreach (Locales::getActivatedLocalesFromSites() as $locale) {
                $page->setTranslation('name', $locale, 'Bestellingen');
                $page->setTranslation('slug', $locale, 'bestellingen');
                $page->setTranslation('content', $locale, [
                    [
                        'data' => [
                            'in_container' => true,
                            'top_margin' => true,
                            'bottom_margin' => true,
                        ],
                        'type' => 'orders-block',
                    ],
                ]);
            }
            $page->save();

            \Dashed\DashedCore\Models\Customsetting::set('orders_page_id', $page->id);

            $page->metadata()->create([
                'noindex' => true,
            ]);
        }

        if (! \Dashed\DashedCore\Models\Customsetting::get('order_page_id')) {
            $page = new \Dashed\DashedPages\Models\Page();
            foreach (Locales::getActivatedLocalesFromSites() as $locale) {
                $page->setTranslation('name', $locale, 'Bestelling');
                $page->setTranslation('slug', $locale, 'bestelling');
                $page->setTranslation('content', $locale, [
                    [
                        'data' => [
                            'in_container' => true,
                            'top_margin' => true,
                            'bottom_margin' => true,
                        ],
                        'type' => 'view-order-block',
                    ],
                ]);
            }
            $page->save();

            \Dashed\DashedCore\Models\Customsetting::set('order_page_id', $page->id);

            $page->metadata()->create([
                'noindex' => true,
            ]);
        }

        if (! \Dashed\DashedCore\Models\Customsetting::get('cart_page_id')) {
            $page = new \Dashed\DashedPages\Models\Page();
            foreach (Locales::getActivatedLocalesFromSites() as $locale) {
                $page->setTranslation('name', $locale, 'Winkelwagen');
                $page->setTranslation('slug', $locale, 'winkelwagen');
                $page->setTranslation('content', $locale, [
                    [
                        'data' => [
                            'in_container' => true,
                            'top_margin' => true,
                            'bottom_margin' => true,
                        ],
                        'type' => 'cart-block',
                    ],
                ]);
            }
            $page->save();

            \Dashed\DashedCore\Models\Customsetting::set('cart_page_id', $page->id);
        }

        if (! \Dashed\DashedCore\Models\Customsetting::get('checkout_page_id')) {
            $page = new \Dashed\DashedPages\Models\Page();
            foreach (Locales::getActivatedLocalesFromSites() as $locale) {
                $page->setTranslation('name', $locale, 'Afrekenen');
                $page->setTranslation('slug', $locale, 'afrekenen');
                $page->setTranslation('content', $locale, [
                    [
                        'data' => [
                            'in_container' => true,
                            'top_margin' => true,
                            'bottom_margin' => true,
                        ],
                        'type' => 'checkout-block',
                    ],
                ]);
            }
            $page->save();

            \Dashed\DashedCore\Models\Customsetting::set('checkout_page_id', $page->id);
        }
    }

    protected function registerPopupTemplates(): void
    {
        if (! class_exists(\Dashed\DashedPopups\PopupTemplates\PopupTemplateRegistry::class)) {
            return;
        }

        $registry = \Dashed\DashedPopups\PopupTemplates\PopupTemplateRegistry::class;

        $registry::register('welkom_10_korting', [
            'label' => 'Welkom: 10% korting (email capture)',
            'attributes' => [
                'type' => 'discount',
                'active' => false,
                'title' => 'Krijg 10% welkomstkorting',
                'discount_percentage' => 10,
                'discount_valid_days' => 14,
                'auto_apply_discount' => true,
                'trigger_type' => 'delay',
                'trigger_value' => 8,
                'show_again_after' => 20160,
            ],
            'blocks' => [
                ['type' => 'heading', 'data' => ['text' => 'Welkom!', 'level' => 'h2']],
                ['type' => 'discount_highlight', 'data' => ['label' => 'Krijg nu', 'value' => '10%', 'suffix' => 'korting']],
                ['type' => 'paragraph', 'data' => ['text' => 'Vul je e-mailadres in en ontvang direct een kortingscode in je mand.']],
                ['type' => 'usp_list', 'data' => ['items' => [
                    'Geldig op alle producten',
                    '14 dagen geldig',
                    'Eenmalig te gebruiken',
                ]]],
            ],
        ]);

        $registry::register('exit_intent_last_chance', [
            'label' => 'Exit-intent: laatste kans (email capture)',
            'attributes' => [
                'type' => 'discount',
                'active' => false,
                'title' => 'Wacht nog even!',
                'discount_percentage' => 10,
                'discount_valid_days' => 14,
                'auto_apply_discount' => true,
                'trigger_type' => 'exit_intent',
                'trigger_value' => 0,
                'show_again_after' => 20160,
            ],
            'blocks' => [
                ['type' => 'heading', 'data' => ['text' => 'Wacht nog even!', 'level' => 'h2']],
                ['type' => 'paragraph', 'data' => ['text' => 'Voor je gaat: een laatste kans op 10% korting op je bestelling.']],
                ['type' => 'discount_highlight', 'data' => ['label' => 'Speciaal voor jou', 'value' => '10%', 'suffix' => 'korting']],
                ['type' => 'usp_list', 'data' => ['items' => [
                    'Geldig op alle producten',
                    '14 dagen geldig',
                    'Eenmalig te gebruiken',
                ]]],
            ],
        ]);

        $registry::register('seasonal_campaign', [
            'label' => 'Seasonal: Black Friday / kerst (email capture)',
            'attributes' => [
                'type' => 'discount',
                'active' => false,
                'title' => 'Speciale aanbieding',
                'discount_percentage' => 15,
                'discount_valid_days' => 7,
                'auto_apply_discount' => true,
                'trigger_type' => 'scroll',
                'trigger_value' => 40,
                'show_again_after' => 10080,
            ],
            'blocks' => [
                ['type' => 'heading', 'data' => ['text' => 'Speciale aanbieding', 'level' => 'h2']],
                ['type' => 'paragraph', 'data' => ['text' => 'Voeg hier je seizoenstekst toe en pas het kortingspercentage aan naar behoefte.']],
                ['type' => 'discount_highlight', 'data' => ['label' => 'Tijdelijk', 'value' => '15%', 'suffix' => 'korting']],
            ],
        ]);
    }
}
