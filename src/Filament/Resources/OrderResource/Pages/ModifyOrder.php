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
                ->title('Deze bestelling kan niet gewijzigd worden')
                ->body('Geannuleerde, geretourneerde, al vervangen of credit-bestellingen kunnen niet via dit scherm aangepast worden.')
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
                ->label('Terug naar bestelling')
                ->icon('heroicon-m-arrow-uturn-left')
                ->color('gray')
                ->url(fn () => route('filament.dashed.resources.orders.view', ['record' => $this->order->id])),
        ];
    }

    /**
     * Zelfde bewoording voor de banner boven het formulier en de
     * bevestigingsmodal van submitAction(), zodat ze niet uit elkaar kunnen
     * lopen.
     */
    protected function routeDescription(bool $inPlace): string
    {
        return $inPlace
            ? 'Deze bestelling wordt zelf aangepast. Er komt geen tweede bestelling bij.'
            : 'Er wordt een vervangende bestelling aangemaakt met het al betaalde bedrag erin verrekend. Deze bestelling wordt ' . ($this->order->hasRealInvoice() ? 'gecrediteerd' : 'geannuleerd') . '.';
    }

    public function modifyOrderForm(Schema $schema): Schema
    {
        $inPlace = OrderModificationService::canModifyInPlace($this->order);

        return $schema
            ->schema([
                Section::make('Wat er gaat gebeuren')
                    ->schema([
                        TextEntry::make('route')
                            ->hiddenLabel()
                            ->state($this->routeDescription($inPlace)),
                    ])
                    ->columnSpanFull(),
                Section::make('Regels')
                    ->schema([
                        Repeater::make('lines')
                            ->hiddenLabel()
                            // Zonder dit krijgt elke regel een UUID-key i.p.v. een
                            // numerieke index, wat submit() niet nodig heeft en de
                            // Livewire-tests op data.lines.0.* onbruikbaar maakt.
                            ->generateUuidUsing(false)
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->searchable()
                                    // name is een translatable JSON-kolom. LIKE op de ruwe
                                    // kolom matcht de opgeslagen JSON, en de labels moeten
                                    // via het model lopen zodat de accessor vertaalt;
                                    // pluck() zou de ruwe JSON teruggeven.
                                    ->getSearchResultsUsing(fn (string $search) => Product::query()
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
                                    ->label('Omschrijving')
                                    ->required()
                                    ->columnSpan(2),
                                TextInput::make('quantity')
                                    ->label('Aantal')
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
                                    ->label('Regeltotaal')
                                    ->helperText('Het totaal van deze regel, niet de stuksprijs')
                                    ->numeric()
                                    ->required()
                                    ->prefix('€'),
                                TextInput::make('vat_rate')
                                    ->label('BTW')
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
                            ->addActionLabel('Regel toevoegen')
                            ->reorderable(false)
                            ->live(),
                    ])
                    ->columnSpanFull(),
                Section::make('Opties')
                    ->schema([
                        Toggle::make('credit_old_order')
                            ->label('Creditfactuur maken voor de oude bestelling')
                            ->helperText('Standaard aan wanneer de bestelling een echt factuurnummer heeft')
                            ->visible(! $inPlace),
                        Toggle::make('already_shipped')
                            ->label('De oude producten zijn al verzonden en komen niet terug')
                            ->helperText('Hiermee blijft de voorraad van de oude regels afgeboekt')
                            ->visible(! $inPlace),
                        Toggle::make('deduct_new_stock')
                            ->label('Voorraad van de nieuwe bestelling afboeken')
                            ->helperText('Zet dit uit bij een administratieve correctie waarbij er niets nieuws verzonden wordt')
                            ->default(true)
                            ->visible(! $inPlace),
                        Toggle::make('products_must_be_returned')
                            ->label('De producten moeten terugkomen van de klant')
                            ->visible(! $inPlace),
                        Toggle::make('send_customer_email')
                            ->label('Klant een wijzigingsmail sturen'),
                        Textarea::make('customer_note')
                            ->label('Toelichting in de mail')
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
            ->label('Wijziging doorvoeren')
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading('Wijziging doorvoeren?')
            ->modalDescription(fn () => $this->buildConfirmationDescription())
            ->modalSubmitActionLabel('Ja, doorvoeren')
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
        // Zelfde formule als OrderTotalsCalculator::recalculate(): korting kan
        // nooit groter zijn dan het (nieuwe) subtotaal.
        $discount = min((float) ($this->order->discount ?? 0), $newSubtotal);
        $newTotal = round($newSubtotal - $discount, 2);
        $oldTotal = (float) $this->order->total;
        $difference = round($newTotal - $oldTotal, 2);

        $paid = (float) $this->order->orderPayments()->where('status', 'paid')->sum('amount');
        $balance = round($newTotal - $paid, 2);

        if ($balance > 0.005) {
            $moneySentence = 'Er blijft ' . CurrencyHelper::formatPrice($balance) . ' te betalen over.';
        } elseif ($balance < -0.005) {
            $moneySentence = 'Er moet ' . CurrencyHelper::formatPrice(abs($balance)) . ' terugbetaald worden.';
        } else {
            $moneySentence = 'Er hoeft niets (meer) betaald te worden.';
        }

        $differenceText = match (true) {
            $difference > 0 => '+' . CurrencyHelper::formatPrice($difference),
            $difference < 0 => '-' . CurrencyHelper::formatPrice(abs($difference)),
            default => CurrencyHelper::formatPrice(0),
        };

        $emailSentence = (bool) ($state['send_customer_email'] ?? true)
            ? 'De klant ontvangt een wijzigingsmail.'
            : 'De klant ontvangt geen wijzigingsmail.';

        return $this->routeDescription($inPlace)
            . ' Huidig totaal: ' . CurrencyHelper::formatPrice($oldTotal)
            . ', nieuw totaal: ' . CurrencyHelper::formatPrice($newTotal)
            . ' (verschil: ' . $differenceText . ').'
            . ' ' . $moneySentence
            . ' ' . $emailSentence;
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
                ->title('Deze bestelling kan niet gewijzigd worden')
                ->body('De bestelling is intussen niet meer wijzigbaar, bijvoorbeeld omdat hij al vervangen, gecrediteerd of geannuleerd is.')
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
                ->title('Een bestelling moet minimaal één regel houden')
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
            ->title('Bestelling gewijzigd')
            ->body('Nieuw totaal: ' . CurrencyHelper::formatPrice($target->fresh()->total))
            ->success()
            ->send();

        $this->redirect(route('filament.dashed.resources.orders.view', ['record' => $target->id]));
    }
}
