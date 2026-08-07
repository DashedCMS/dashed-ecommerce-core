<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Pages;

use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Resources\Pages\Page;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Infolists\Components\TextEntry;
use Dashed\DashedEcommerceCore\Models\Product;
use Filament\Schemas\Components\Utilities\Get;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Classes\OrderTotalsCalculator;
use Dashed\DashedEcommerceCore\Classes\OrderModificationService;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;

class ModifyOrder extends Page implements HasSchemas
{
    protected static string $resource = OrderResource::class;
    protected string $view = 'dashed-ecommerce-core::orders.modify-order';

    public Order $order;
    public ?array $data = [];

    public function mount($record): void
    {
        $this->order = Order::findOrFail($record);

        if (! $this->order->isModifiable()) {
            Notification::make()
                ->title(__('Deze bestelling kan niet gewijzigd worden'))
                ->body(__('Geannuleerde, geretourneerde, al vervangen of credit-bestellingen kunnen niet via dit scherm aangepast worden.'))
                ->danger()
                ->send();

            $this->redirect(route('filament.dashed.resources.orders.view', ['record' => $this->order->id]));

            return;
        }

        $this->modifyOrderForm->fill([
            'deduct_new_stock' => true,
            'lines' => $this->order->orderProducts->map(fn ($orderProduct) => [
                'order_product_id' => $orderProduct->id,
                'product_id' => $orderProduct->product_id,
                'name' => $orderProduct->name,
                'quantity' => (int) $orderProduct->quantity,
                'price' => (float) $orderProduct->price,
                'vat_rate' => (float) ($orderProduct->vat_rate ?? 21),
                'product_extras' => $orderProduct->product_extras ?? [],
            ])->values()->all(),
            'send_customer_email' => true,
            'already_shipped' => false,
            'products_must_be_returned' => false,
            'credit_old_order' => $this->order->hasRealInvoice(),
            'customer_note' => null,
        ]);
    }

    public function getTitle(): string
    {
        return "Bestelling {$this->order->invoice_id} wijzigen";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToView')
                ->label(__('Terug naar bestelling'))
                ->icon('heroicon-m-arrow-uturn-left')
                ->color('gray')
                ->url(fn () => route('filament.dashed.resources.orders.view', ['record' => $this->order->id])),
        ];
    }

    /**
     * Zelfde bewoording voor de banner boven het formulier en de
     * bevestigingsmodal van submitAction(), zodat ze niet uit elkaar kunnen
     * lopen.
     *
     * $creditOldOrder komt bewust van buiten en wordt niet zelf uit
     * $this->order->hasRealInvoice() afgeleid: de beheerder kan de schakelaar
     * "Creditfactuur maken voor de oude bestelling" omzetten, en submit() geeft
     * precies die schakelaar aan de service door. Zou deze zin de schakelaar
     * negeren, dan zegt de bevestiging "gecrediteerd" terwijl de order
     * geannuleerd wordt (of andersom) — juist op de enige onomkeerbare stap in
     * dit scherm.
     */
    protected function routeDescription(bool $inPlace, bool $creditOldOrder): string
    {
        return $inPlace
            ? __('Deze bestelling wordt zelf aangepast. Er komt geen tweede bestelling bij.')
            : __('Er wordt een vervangende bestelling aangemaakt met het al betaalde bedrag erin verrekend. Deze bestelling wordt :actie.', ['actie' => $creditOldOrder ? __('gecrediteerd') : __('geannuleerd')]);
    }

    public function modifyOrderForm(Schema $schema): Schema
    {
        $inPlace = OrderModificationService::canModifyInPlace($this->order);

        return $schema
            ->schema([
                Section::make(__('Wat er gaat gebeuren'))
                    ->schema([
                        TextEntry::make('route')
                            ->hiddenLabel()
                            // Bij het renderen van de banner is er nog geen
                            // formulierstaat; de schakelaar staat dan nog op zijn
                            // default. De modal leest wél de actuele staat.
                            ->state($this->routeDescription($inPlace, $this->order->hasRealInvoice())),
                    ])
                    ->columnSpanFull(),
                Section::make(__('Regels'))
                    ->schema([
                        Repeater::make('lines')
                            ->hiddenLabel()
                            // Zonder dit krijgt elke regel een UUID-key i.p.v. een
                            // numerieke index, wat submit() niet nodig heeft en de
                            // Livewire-tests op data.lines.0.* onbruikbaar maakt.
                            ->generateUuidUsing(false)
                            ->schema([
                                Select::make('product_id')
                                    ->label(__('Product'))
                                    ->searchable()
                                    // name is een translatable JSON-kolom. LIKE op de ruwe
                                    // kolom matcht de opgeslagen JSON, en de labels moeten
                                    // via het model lopen zodat de accessor vertaalt;
                                    // pluck() zou de ruwe JSON teruggeven.
                                    // thisSite() op de site van de order (niet op de
                                    // actieve site van de beheerder): op een
                                    // multi-site-installatie hoort er geen product
                                    // van een andere webshop op deze bestelling te
                                    // kunnen belanden. replaceWithNewOrder() zet de
                                    // site_id van de order ook zorgvuldig terug.
                                    ->getSearchResultsUsing(fn (string $search) => Product::query()
                                        ->thisSite($this->order->site_id)
                                        ->where('name', 'LIKE', "%{$search}%")
                                        ->limit(25)
                                        ->get()
                                        ->mapWithKeys(fn (Product $product) => [$product->id => $product->name])
                                        ->all())
                                    ->getOptionLabelUsing(fn ($value) => Product::find($value)?->name)
                                    ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                        $product = $state ? Product::find($state) : null;
                                        if ($product) {
                                            $set('name', $product->name);
                                            self::setLineTotalFromProduct($product, $get('quantity'), $set);
                                        }
                                    })
                                    ->live()
                                    ->columnSpan(2),
                                TextInput::make('name')
                                    ->label(__('Omschrijving'))
                                    ->required()
                                    ->columnSpan(2),
                                TextInput::make('quantity')
                                    ->label(__('Aantal'))
                                    ->numeric()
                                    ->minValue(1)
                                    ->required()
                                    ->default(1)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, $old, callable $set, Get $get) {
                                        $previousQuantity = (int) ($old ?? 0);
                                        $previousPrice = $get('price');
                                        $newQuantity = max(1, (int) ($state ?? 1));

                                        // Schaal vanaf de eigen regelprijs (kan onderhandeld of
                                        // historisch zijn, of een extras-toeslag bevatten die niet
                                        // in de catalogusprijs zit) zodra de regel al een prijs en
                                        // een vorige kwantiteit heeft. Alleen wanneer die basis
                                        // ontbreekt (nieuwe, nog lege regel) valt dit terug op de
                                        // catalogusprijs van het gekoppelde product.
                                        if ($previousQuantity > 0 && $previousPrice !== null && $previousPrice !== '') {
                                            $unitPrice = (float) $previousPrice / $previousQuantity;
                                            $set('price', round($unitPrice * $newQuantity, 2));

                                            return;
                                        }

                                        $productId = $get('product_id');
                                        $product = $productId ? Product::find($productId) : null;
                                        if ($product) {
                                            self::setLineTotalFromProduct($product, $state, $set);
                                        }
                                    }),
                                TextInput::make('price')
                                    ->label(__('Regeltotaal'))
                                    ->helperText(__('Het totaal van deze regel, niet de stuksprijs'))
                                    ->numeric()
                                    ->required()
                                    ->prefix('€'),
                                TextInput::make('vat_rate')
                                    ->label(__('BTW'))
                                    ->numeric()
                                    ->required()
                                    ->default(21)
                                    ->suffix('%'),
                                // Niet bewerkbaar in deze eerste versie; bestaat alleen om de
                                // extras van een ongewijzigde regel de round-trip te laten
                                // overleven (writeLines() herbouwt alle regels vanaf nul).
                                Hidden::make('product_extras'),
                            ])
                            ->columns(4)
                            ->addActionLabel(__('Regel toevoegen'))
                            ->reorderable(false)
                            ->live(),
                    ])
                    ->columnSpanFull(),
                Section::make(__('Opties'))
                    ->schema([
                        Toggle::make('credit_old_order')
                            ->label(__('Creditfactuur maken voor de oude bestelling'))
                            ->helperText(__('Standaard aan wanneer de bestelling een echt factuurnummer heeft'))
                            ->visible(! $inPlace),
                        Toggle::make('already_shipped')
                            ->label(__('De oude producten zijn al verzonden en komen niet terug'))
                            ->helperText(__('Hiermee blijft de voorraad van de oude regels afgeboekt'))
                            ->visible(! $inPlace),
                        Toggle::make('deduct_new_stock')
                            ->label(__('Voorraad van de nieuwe bestelling afboeken'))
                            ->helperText(__('Zet dit uit bij een administratieve correctie waarbij er niets nieuws verzonden wordt'))
                            ->default(true)
                            ->visible(! $inPlace),
                        Toggle::make('products_must_be_returned')
                            ->label(__('De producten moeten terugkomen van de klant'))
                            ->visible(! $inPlace),
                        Toggle::make('send_customer_email')
                            ->label(__('Klant een wijzigingsmail sturen')),
                        Textarea::make('customer_note')
                            ->label(__('Toelichting in de mail'))
                            ->rows(3),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    /**
     * Zet het regeltotaal (niet de stuksprijs) op basis van de kwantiteit.
     *
     * Gebruikt Product::getRawOriginal('current_price'): de ruwe, opgeslagen
     * kolomwaarde vóór de currentPrice-accessor. Die accessor rekent om naar
     * ex-BTW zodra de ingelogde beheerder `show_prices_ex_vat` aan heeft
     * staan, wat de opgeslagen orderregel afhankelijk zou maken van wélke
     * beheerder toevallig aan het wijzigen was. De ruwe kolom staat op
     * dezelfde grondslag als orderregels (bepaald door de shopbrede
     * taxes_prices_include_taxes-instelling, niet door een persoonlijke
     * weergavevoorkeur) en is ongevoelig voor prijsgroep/custom-pricing van
     * de ingelogde gebruiker, dus voor elke beheerder identiek.
     */
    protected static function setLineTotalFromProduct(Product $product, mixed $quantity, callable $set): void
    {
        // current_price is nullable en wordt pas gevuld zodra
        // Product::calculatePrices() ooit gedraaid heeft. Zonder fallback
        // zou een product waarvoor dat nog niet gebeurd is een regeltotaal
        // van 0,00 opleveren; de ruwe price-kolom is de volgende beste basis.
        $unitPrice = $product->getRawOriginal('current_price') ?? $product->getRawOriginal('price');
        $unitPrice = (float) $unitPrice;
        $quantity = max(1, (int) ($quantity ?? 1));

        $set('price', round($unitPrice * $quantity, 2));
    }

    /**
     * Deze pagina herschrijft onomkeerbaar een echte bestelling; niet
     * rechtstreeks vanaf de knop laten gaan. modalDescription() wordt bij
     * elke keer openen opnieuw geëvalueerd, dus die leest de actuele
     * formulierstaat op het moment van klikken, niet de staat bij het laden
     * van de pagina.
     */
    public function submitAction(): Action
    {
        return Action::make('submitAction')
            ->label(__('Wijziging doorvoeren'))
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading(__('Wijziging doorvoeren?'))
            ->modalDescription(fn () => $this->buildConfirmationDescription())
            ->modalSubmitActionLabel(__('Ja, doorvoeren'))
            ->action(fn () => $this->submit());
    }

    protected function buildConfirmationDescription(): string
    {
        $state = $this->modifyOrderForm->getState();

        $inPlace = OrderModificationService::canModifyInPlace($this->order);

        $newSubtotal = round(
            collect($state['lines'] ?? [])->sum(fn (array $line) => (float) ($line['price'] ?? 0)),
            2
        );
        // Via dezelfde methode als OrderTotalsCalculator::recalculate() straks
        // gebruikt, zodat de bevestiging niet iets anders kan tonen dan er
        // weggeschreven wordt. Bij een procentuele kortingscode wordt de korting
        // hier dus al over het nieuwe subtotaal herrekend.
        $discountBreakdown = OrderTotalsCalculator::discountBreakdownForLines($this->order, $this->confirmationLines($state));
        $discount = $discountBreakdown['discount'];
        $newTotal = round($newSubtotal - $discount, 2);
        $oldTotal = (float) $this->order->total;
        $difference = round($newTotal - $oldTotal, 2);

        $paid = (float) $this->order->orderPayments()->where('status', 'paid')->sum('amount');
        $balance = round($newTotal - $paid, 2);

        if ($balance > 0.005) {
            $moneySentence = __('Er blijft :bedrag te betalen over.', ['bedrag' => CurrencyHelper::formatPrice($balance)]);
        } elseif ($balance < -0.005) {
            $moneySentence = __('Er moet :bedrag terugbetaald worden.', ['bedrag' => CurrencyHelper::formatPrice(abs($balance))]);
        } else {
            $moneySentence = __('Er hoeft niets (meer) betaald te worden.');
        }

        $differenceText = match (true) {
            $difference > 0 => '+' . CurrencyHelper::formatPrice($difference),
            $difference < 0 => '-' . CurrencyHelper::formatPrice(abs($difference)),
            default => CurrencyHelper::formatPrice(0),
        };

        $emailSentence = (bool) ($state['send_customer_email'] ?? true)
            ? __('De klant ontvangt een wijzigingsmail.')
            : __('De klant ontvangt geen wijzigingsmail.');

        // De korting expliciet noemen zodra er een is: bij een procentuele code
        // beweegt hij mee met de nieuwe regels en dan moet de beheerder kunnen
        // zien welk bedrag er daadwerkelijk toegepast wordt.
        $discountSentence = $discount > 0.005
            ? ' ' . __('Toegepaste korting: :bedrag.', ['bedrag' => CurrencyHelper::formatPrice($discount)])
            : '';

        // Wordt de korting afgetopt op het nieuwe subtotaal, dan raakt de klant
        // het verschil kwijt; bij een cadeaubon is dat echt saldo dat niet
        // automatisch terugkomt. Dat hoort de beheerder te zien vóór hij
        // bevestigt, niet pas achteraf in het orderlogboek.
        $capSentence = $discountBreakdown['reduced_by'] > 0.005
            ? ' ' . __('Let op: :zin', ['zin' => lcfirst(OrderTotalsCalculator::cappedDiscountSentence($this->order, $discountBreakdown))])
            : '';

        return $this->routeDescription($inPlace, (bool) ($state['credit_old_order'] ?? $this->order->hasRealInvoice()))
            . ' ' . __('Huidig totaal: :oud, nieuw totaal: :nieuw (verschil: :verschil).', [
                'oud' => CurrencyHelper::formatPrice($oldTotal),
                'nieuw' => CurrencyHelper::formatPrice($newTotal),
                'verschil' => $differenceText,
            ])
            . $discountSentence
            . $capSentence
            . ' ' . $moneySentence
            . ' ' . $emailSentence;
    }

    /**
     * De regels uit de formulierstaat in de vorm die
     * OrderTotalsCalculator::discountForLines() verwacht. De sku staat niet in
     * het formulier (die is niet bewerkbaar) maar is wel nodig om verzend- en
     * betaalkosten van een procentuele korting uit te sluiten. Hij wordt bepaald
     * met exact dezelfde methode als writeLines() straks gebruikt
     * (OrderModificationService::skuForLine()), zodat de bevestiging geen
     * kortingsbedrag kan tonen dat afwijkt van wat er weggeschreven wordt: een
     * regel waarop de beheerder het product omzette, telt hier dan net zo goed
     * als een gewone productregel mee in plaats van als kostenregel.
     *
     * @param  array<string, mixed>  $state
     * @return array<int, array{price: float, quantity: int, product_id: int|null, sku: string|null}>
     */
    protected function confirmationLines(array $state): array
    {
        $sourceLines = $this->order->orderProducts->keyBy('id');

        return collect($state['lines'] ?? [])
            ->map(fn (array $line) => [
                'price' => (float) ($line['price'] ?? 0),
                'quantity' => (int) ($line['quantity'] ?? 1),
                'product_id' => $line['product_id'] ?? null,
                'sku' => OrderModificationService::skuForLine($line, $sourceLines->get($line['order_product_id'] ?? null)),
            ])
            ->all();
    }

    public function submit(): void
    {
        // Een tweede tabblad kan de order intussen elders vervangen,
        // gecrediteerd of geannuleerd hebben. Zonder deze herhaalde check
        // zou submit() alsnog replaceWithNewOrder() aanroepen en op de
        // LogicException stuiten, wat als een onbehandelde 500 naar buiten
        // komt in plaats van een nette melding.
        if (! $this->order->fresh()->isModifiable()) {
            Notification::make()
                ->title(__('Deze bestelling kan niet gewijzigd worden'))
                ->body(__('De bestelling is intussen niet meer wijzigbaar, bijvoorbeeld omdat hij al vervangen, gecrediteerd of geannuleerd is.'))
                ->danger()
                ->send();

            $this->redirect(route('filament.dashed.resources.orders.view', ['record' => $this->order->id]));

            return;
        }

        $state = $this->modifyOrderForm->getState();

        $lines = collect($state['lines'] ?? [])
            ->map(fn (array $line) => [
                'order_product_id' => $line['order_product_id'] ?? null,
                'product_id' => $line['product_id'] ?? null,
                'name' => $line['name'],
                'quantity' => (int) $line['quantity'],
                'price' => (float) $line['price'],
                'vat_rate' => (float) $line['vat_rate'],
                'product_extras' => $line['product_extras'] ?? [],
            ])
            ->all();

        if (! count($lines)) {
            Notification::make()
                ->title(__('Een bestelling moet minimaal één regel houden'))
                ->danger()
                ->send();

            return;
        }

        $options = [
            'send_customer_email' => (bool) ($state['send_customer_email'] ?? true),
            'customer_note' => $state['customer_note'] ?? null,
            'already_shipped' => (bool) ($state['already_shipped'] ?? false),
            'deduct_new_stock' => (bool) ($state['deduct_new_stock'] ?? true),
            'products_must_be_returned' => (bool) ($state['products_must_be_returned'] ?? false),
            'credit_old_order' => (bool) ($state['credit_old_order'] ?? $this->order->hasRealInvoice()),
        ];

        if (OrderModificationService::canModifyInPlace($this->order)) {
            OrderModificationService::applyInPlace($this->order, $lines, $options);
            $target = $this->order;
        } else {
            $target = OrderModificationService::replaceWithNewOrder($this->order, $lines, $options);
        }

        Notification::make()
            ->title(__('Bestelling gewijzigd'))
            ->body(__('Nieuw totaal: :bedrag', ['bedrag' => CurrencyHelper::formatPrice($target->fresh()->total)]))
            ->success()
            ->send();

        $this->redirect(route('filament.dashed.resources.orders.view', ['record' => $target->id]));
    }
}
