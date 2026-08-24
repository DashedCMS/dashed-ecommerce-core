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
use Dashed\DashedEcommerceCore\Classes\Orders;
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
                'discount' => (float) ($orderProduct->discount ?? 0),
                'vat_rate' => (float) ($orderProduct->vat_rate ?? 21),
                'product_extras' => $orderProduct->product_extras ?? [],
            ])->values()->all(),
            'send_customer_email' => true,
            'already_shipped' => false,
            'products_must_be_returned' => false,
            'credit_old_order' => $this->order->hasRealInvoice(),
            'old_order_fulfillment_status' => 'handled',
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
                                            $this->setLineTotalFromProduct($product, $get('quantity'), $set);
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
                                            $newPrice = round($unitPrice * $newQuantity, 2);
                                            $set('price', $newPrice);
                                            self::scaleLineDiscount($set, $get, (float) $previousPrice, $newPrice);

                                            return;
                                        }

                                        $productId = $get('product_id');
                                        $product = $productId ? Product::find($productId) : null;
                                        if ($product) {
                                            $this->setLineTotalFromProduct($product, $state, $set);
                                        }
                                    }),
                                TextInput::make('price')
                                    ->label(__('Regeltotaal'))
                                    ->helperText(__('Het totaal van deze regel zoals de klant het betaalt, dus inclusief een eventuele korting en niet de stuksprijs'))
                                    ->numeric()
                                    ->required()
                                    ->prefix('€')
                                    ->live(onBlur: true)
                                    // De korting is het deel van deze prijs dat de klant niet
                                    // betaalt. Halveert de beheerder het regeltotaal, dan hoort
                                    // dat deel mee te halveren; bleef het staan, dan claimt de
                                    // factuur een korting die niet meer in de prijs zit.
                                    ->afterStateUpdated(fn ($state, $old, callable $set, Get $get) => self::scaleLineDiscount($set, $get, (float) $old, (float) $state)),
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
                                // De korting die in de regelprijs verwerkt zit. Niet bewerkbaar:
                                // de beheerder stelt de prijs vast, niet de opbouw daarvan. Wel
                                // in de staat, omdat hij het subtotaal en de kortingsregel op de
                                // factuur bepaalt en met elke prijswijziging meebeweegt.
                                Hidden::make('discount'),
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
                        Select::make('old_order_fulfillment_status')
                            ->label(__('Status van de oude bestelling'))
                            ->helperText(__('De oude bestelling is vervangen en hoeft niet meer opgepakt te worden. De klant krijgt hiervan geen statusmail.'))
                            ->options(collect(Orders::getFulfillmentStatusses())->map(fn (string $label) => __($label))->all())
                            ->default('handled')
                            ->selectablePlaceholder(false)
                            ->required()
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
    protected function setLineTotalFromProduct(Product $product, mixed $quantity, callable $set): void
    {
        // current_price is nullable en wordt pas gevuld zodra
        // Product::calculatePrices() ooit gedraaid heeft. Zonder fallback
        // zou een product waarvoor dat nog niet gebeurd is een regeltotaal
        // van 0,00 opleveren; de ruwe price-kolom is de volgende beste basis.
        $unitPrice = $product->getRawOriginal('current_price') ?? $product->getRawOriginal('price');
        $unitPrice = (float) $unitPrice;
        $quantity = max(1, (int) ($quantity ?? 1));

        // De catalogusprijs is de prijs vóór een kortingscode, terwijl dit veld
        // de prijs is die de klant betaalt. Draagt de order een procentuele
        // code, dan hoort die er dus meteen op: zonder dit rekent een product
        // dat aan een bestelling met 10% korting wordt toegevoegd stilzwijgend
        // de volle prijs. Per stuk afronden en dan pas vermenigvuldigen, net als
        // de winkelwagen (Product::getShoppingCartItemPrice() stap 10), zodat
        // een regel via dit scherm op dezelfde cent uitkomt als via de shop.
        $percentage = OrderTotalsCalculator::percentageForProduct($this->order, $product->id);
        $discountedUnitPrice = $percentage > 0
            ? round($unitPrice * (100 - $percentage) / 100, 2)
            : $unitPrice;

        $price = round($discountedUnitPrice * $quantity, 2);

        $set('price', $price);
        $set('discount', round($unitPrice * $quantity - $price, 2));
    }

    /**
     * Laat de korting van een regel meebewegen met zijn prijs.
     *
     * De regelprijs is de prijs ná korting, dus de korting is een deel van wat
     * er vóór korting stond. Verandert het regeltotaal, dan verandert dat deel
     * evenredig mee; bij een procentuele code blijft de verhouding daarmee
     * precies het percentage van de code. Wordt de prijs op nul gezet, dan
     * blijft er geen korting over om te verdelen.
     */
    protected static function scaleLineDiscount(callable $set, Get $get, float $previousPrice, float $newPrice): void
    {
        $discount = (float) ($get('discount') ?? 0);

        if ($discount <= 0) {
            return;
        }

        if ($previousPrice <= 0 || $newPrice <= 0) {
            $set('discount', 0.0);

            return;
        }

        $set('discount', round($discount * $newPrice / $previousPrice, 2));
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

        // Via dezelfde methode als OrderTotalsCalculator::recalculate() straks
        // gebruikt, en over regels die net zo zijn samengesteld als writeLines()
        // ze wegschrijft, zodat de bevestiging niet iets anders kan tonen dan er
        // weggeschreven wordt.
        $breakdown = OrderTotalsCalculator::breakdownForLines($this->order, $this->confirmationLines($state));
        $discount = $breakdown['discount'];
        $newTotal = $breakdown['total'];
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

        // Alleen op de vervangroute: bij een wijziging in plaats is er geen
        // oude bestelling die achterblijft.
        $oldStatus = $state['old_order_fulfillment_status'] ?? 'handled';
        $oldStatusSentence = $inPlace
            ? ''
            : ' ' . __('De oude bestelling blijft achter op :status.', [
                'status' => __(Orders::getFulfillmentStatusses()[$oldStatus] ?? $oldStatus),
            ]);

        // De korting expliciet noemen zodra er een is. De regelprijzen zijn de
        // prijzen ná korting, dus dit bedrag verlaagt het nieuwe totaal niet
        // nog een keer; het laat zien hoe het subtotaal is opgebouwd.
        $discountSentence = $discount > 0.005
            ? ' ' . __('Toegepaste korting: :bedrag.', ['bedrag' => CurrencyHelper::formatPrice($discount)])
            : '';

        // Wordt de korting afgetopt op het nieuwe subtotaal, dan raakt de klant
        // het verschil kwijt; bij een cadeaubon is dat echt saldo dat niet
        // automatisch terugkomt. Dat hoort de beheerder te zien vóór hij
        // bevestigt, niet pas achteraf in het orderlogboek.
        $capSentence = $breakdown['reduced_by'] > 0.005
            ? ' ' . __('Let op: :zin', ['zin' => lcfirst(OrderTotalsCalculator::cappedDiscountSentence($this->order, $breakdown))])
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
            . ' ' . $emailSentence
            . $oldStatusSentence;
    }

    /**
     * De regels uit de formulierstaat in de vorm die
     * OrderTotalsCalculator::breakdownForLines() verwacht.
     *
     * De korting per regel staat niet in het formulier (die is niet bewerkbaar)
     * maar bepaalt wel het subtotaal en de kortingsregel. Hij wordt hier met
     * exact dezelfde methode bepaald als writeLines() straks gebruikt
     * (OrderModificationService::discountForLine()), zodat de bevestiging geen
     * bedrag kan tonen dat afwijkt van wat er weggeschreven wordt.
     *
     * @param  array<string, mixed>  $state
     * @return array<int, array{price: float, discount: float}>
     */
    protected function confirmationLines(array $state): array
    {
        $sourceLines = $this->order->orderProducts->keyBy('id');

        return collect($state['lines'] ?? [])
            ->map(fn (array $line) => [
                'price' => (float) ($line['price'] ?? 0),
                'discount' => OrderModificationService::discountForLine($line, $sourceLines->get($line['order_product_id'] ?? null)),
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
                // Meegeven en niet aan writeLines() overlaten: de formulierstaat
                // is hier de bron, en die heeft de korting al met elke prijs- en
                // aantalwijziging mee laten schalen.
                'discount' => (float) ($line['discount'] ?? 0),
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
            'old_order_fulfillment_status' => $state['old_order_fulfillment_status'] ?? 'handled',
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
