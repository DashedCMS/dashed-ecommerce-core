<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Pages;

use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Resources\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Classes\OrderModificationService;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;

class ModifyOrder extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string $resource = OrderResource::class;
    protected string $view = 'dashed-ecommerce-core::orders.modify-order';

    public Order $order;
    public ?array $data = [];

    public function mount($record): void
    {
        $this->order = Order::findOrFail($record);

        $this->modifyOrderForm->fill([
            'deduct_new_stock' => true,
            'lines' => $this->order->orderProducts->map(fn ($orderProduct) => [
                'order_product_id' => $orderProduct->id,
                'product_id' => $orderProduct->product_id,
                'name' => $orderProduct->name,
                'quantity' => (int) $orderProduct->quantity,
                'price' => (float) $orderProduct->price,
                'vat_rate' => (float) ($orderProduct->vat_rate ?? 21),
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

    public function modifyOrderForm(Schema $schema): Schema
    {
        $inPlace = OrderModificationService::canModifyInPlace($this->order);

        return $schema
            ->schema([
                Section::make('Wat er gaat gebeuren')
                    ->schema([
                        TextEntry::make('route')
                            ->hiddenLabel()
                            ->state($inPlace
                                ? 'Deze bestelling wordt zelf aangepast. Er komt geen tweede bestelling bij.'
                                : 'Er wordt een vervangende bestelling aangemaakt met het al betaalde bedrag erin verrekend. Deze bestelling wordt ' . ($this->order->hasRealInvoice() ? 'gecrediteerd' : 'geannuleerd') . '.'),
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
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        $product = $state ? Product::find($state) : null;
                                        if ($product) {
                                            $set('name', $product->name);
                                            // current_price is de kolom; currentPrice bestaat niet
                                            // als attribuut op Product.
                                            $set('price', (float) $product->current_price);
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
                                    ->default(1),
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

    public function submit(): void
    {
        $state = $this->modifyOrderForm->getState();

        $lines = collect($state['lines'] ?? [])
            ->map(fn (array $line) => [
                'order_product_id' => $line['order_product_id'] ?? null,
                'product_id' => $line['product_id'] ?? null,
                'name' => $line['name'],
                'quantity' => (int) $line['quantity'],
                'price' => (float) $line['price'],
                'vat_rate' => (float) $line['vat_rate'],
                'product_extras' => [],
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
