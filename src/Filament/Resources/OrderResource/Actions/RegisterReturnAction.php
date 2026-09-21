<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Schemas\Components\Utilities\Get;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Models\ReturnReason;
use Dashed\DashedEcommerceCore\Services\OrderReturn\ReturnableLines;
use Dashed\DashedEcommerceCore\Services\OrderReturn\ReturnRegistrar;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderReturnResource;
use Dashed\DashedEcommerceCore\Services\OrderReturn\OrderLookupService;

/**
 * "Retour aanmelden" op een bestelling en "Nieuwe retour" boven de
 * retourlijst. De tweede vorm heeft een zoekveld voor de bestelling; verder
 * is het formulier hetzelfde en gaat alles door ReturnRegistrar.
 */
class RegisterReturnAction
{
    public static function forOrder(Order $order): Action
    {
        return self::base()
            // Op een creditorder heeft de knop geen zin; in elk ander geval
            // blijft hij staan en zegt de tooltip waarom hij niet kan.
            ->visible(fn () => ! $order->credit_for_order_id)
            ->disabled(fn () => self::blockingReason($order) !== null)
            ->tooltip(fn () => self::blockingReason($order))
            ->schema(fn () => self::lineFields($order))
            ->action(function (array $data) use ($order) {
                self::submit($order, $data);
            });
    }

    public static function withOrderPicker(): Action
    {
        return self::base()
            ->label(__('Nieuwe retour'))
            ->schema(fn () => [
                Select::make('order_id')
                    ->label(__('Bestelling'))
                    ->required()
                    ->searchable()
                    ->live()
                    // Order heeft zijn eigen scopeSearch (breed genoeg voor dit
                    // veld: naam, e-mail, factuurnummer). RelationshipSearchQuery
                    // geeft alleen de kant-en-klare optionsarray terug, die je
                    // niet meer met whereIn/whereNull kunt versmallen, dus deze
                    // extra filters lopen rechtstreeks op de query eronder. De
                    // search()-scope zelf is een reeks orWhere's zonder eigen
                    // groep; zonder de where(fn...)-wrapper zouden whereIn en
                    // whereNull door AND-voor-OR-precedentie alleen aan de
                    // laatste orWhere vastzitten en de rest van de treffers
                    // ongefilterd doorlaten.
                    ->getSearchResultsUsing(fn (string $search) => Order::query()
                        ->where(fn ($q) => $q->search($search))
                        ->whereIn('status', OrderLookupService::ELIGIBLE_STATUSES)
                        ->whereNull('credit_for_order_id')
                        ->limit(25)
                        ->get()
                        ->mapWithKeys(fn (Order $o) => [$o->id => ($o->invoice_id ?: '#' . $o->id) . ' - ' . $o->name . ' - ' . $o->email])
                        ->all())
                    ->getOptionLabelUsing(fn ($value) => ($o = Order::find($value)) ? (($o->invoice_id ?: '#' . $o->id) . ' - ' . $o->name) : null),
                Fieldset::make(__('Regels'))
                    ->columnSpanFull()
                    ->visible(fn (Get $get) => filled($get('order_id')))
                    ->schema(function (Get $get) {
                        $order = Order::find($get('order_id'));

                        return $order ? self::lineFields($order) : [];
                    }),
            ])
            ->action(function (array $data) {
                $order = Order::find($data['order_id'] ?? null);
                if (! $order) {
                    Notification::make()->danger()->title(__('Kies een bestelling.'))->send();

                    return;
                }
                self::submit($order, $data);
            });
    }

    protected static function base(): Action
    {
        return Action::make('registerReturn')
            ->label(__('Retour aanmelden'))
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('warning')
            ->button()
            ->modalHeading(__('Retour aanmelden'))
            ->modalDescription(__('De retour begint direct als goedgekeurd. Met "Klant informeren" krijgt de klant de goedkeuringsmail met statuslink en, als dat is ingericht, een retourlabel.'));
    }

    /** Waarom de knop niet kan, of null als het mag. */
    public static function blockingReason(Order $order): ?string
    {
        if (! in_array($order->status, OrderLookupService::ELIGIBLE_STATUSES, true)) {
            return __('Alleen een betaalde bestelling kan geretourneerd worden.');
        }
        if ($order->credit_for_order_id) {
            return __('Een creditorder kan niet geretourneerd worden.');
        }
        $open = OrderReturn::query()->where('order_id', $order->id)->open()->first();
        if ($open) {
            return __('Er staat al een open retour (#:id).', ['id' => $open->id]);
        }
        if (ReturnableLines::forOrder($order)->isEmpty()) {
            return __('Er is niets meer te retourneren.');
        }

        return null;
    }

    /** @return array<int, \Filament\Schemas\Components\Component> */
    protected static function lineFields(Order $order): array
    {
        $reasons = ReturnReason::active()->get()
            ->mapWithKeys(fn (ReturnReason $r) => [$r->id => $r->getTranslation('label', app()->getLocale())])
            ->all();

        $fields = [];
        foreach (ReturnableLines::forOrder($order) as $orderProduct) {
            $remaining = ReturnableLines::remaining($orderProduct);
            $fields[] = Fieldset::make($orderProduct->name)
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    TextInput::make("lines.{$orderProduct->id}.quantity")
                        ->label(__('Aantal'))
                        ->helperText(__('Nog te retourneren: :restant', ['restant' => $remaining]))
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->maxValue($remaining)
                        ->default(0),
                    Select::make("lines.{$orderProduct->id}.return_reason_id")
                        ->label(__('Reden'))
                        ->options($reasons)
                        ->nullable(),
                    TextInput::make("lines.{$orderProduct->id}.reason_note")
                        ->label(__('Toelichting'))
                        ->maxLength(500),
                ]);
        }

        $isBol = $order->order_origin === 'Bol';

        $fields[] = Textarea::make('admin_note')->label(__('Notitie (optioneel)'));
        $fields[] = Toggle::make('notify_customer')
            ->label(__('Klant informeren'))
            ->helperText($isBol ? __('Uit voor een Bol-bestelling: Bol informeert die klant.') : __('Goedkeuringsmail met statuslink en, waar ingericht, een retourlabel.'))
            ->default(! $isBol)
            ->disabled($isBol);

        return $fields;
    }

    protected static function submit(Order $order, array $data): void
    {
        $lines = [];
        foreach ((array) ($data['lines'] ?? []) as $orderProductId => $line) {
            $quantity = (int) ($line['quantity'] ?? 0);
            if ($quantity < 1) {
                continue;
            }
            $lines[] = [
                'order_product_id' => (int) $orderProductId,
                'quantity' => $quantity,
                'return_reason_id' => $line['return_reason_id'] ?? null,
                'reason_note' => $line['reason_note'] ?? null,
            ];
        }

        try {
            $return = app(ReturnRegistrar::class)->register($order, $lines, [
                'admin_note' => $data['admin_note'] ?? null,
                'notify_customer' => (bool) ($data['notify_customer'] ?? true),
            ]);
        } catch (\InvalidArgumentException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }

        Notification::make()
            ->success()
            ->title(__('Retour aangemeld'))
            ->actions([
                Action::make('open')->label(__('Open retour'))->url(OrderReturnResource::getUrl('view', ['record' => $return->id])),
            ])
            ->send();
    }
}
