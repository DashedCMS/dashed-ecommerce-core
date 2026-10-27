<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Orders;

use Livewire\Component;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Classes\TikTokHelper;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Dashed\DashedEcommerceCore\Classes\PhoneNormalizer;
use Dashed\DashedEcommerceCore\Services\Meta\MetaBrowserPayload;
use Dashed\DashedEcommerceCore\Services\Payments\PspStatusResolver;
use Dashed\DashedEcommerceCore\Services\Payments\ReturnPaymentLookup;

class ViewOrder extends Component
{
    public Order $order;
    public Collection $orderProducts;
    public Collection $notes;
    public array $customOrderFields = [];

    public function mount()
    {
        // Op de hash van de betaling, psp_id alleen kort na het betalen.
        $orderPayment = ReturnPaymentLookup::fromRequest(request());

        if (! $orderPayment) {
            return redirect('/')->with('error', Translation::get('order-not-found', 'checkout', 'The order could not be found'));
        }

        $order = $orderPayment->order;

        $hasAccessToOrder = false;

        if ($order) {
            $hasAccessToOrder = true;
        }

        if (! $hasAccessToOrder) {
            return redirect('/')->with('error', Translation::get('order-not-found', 'checkout', 'The order could not be found'));
        }

        $lock = Cache::lock('order.check-payment.' . $order->id, 10);

        try {
            if ($lock->get()) {
                // Dezelfde route als de exchange en de complete-pagina: bij een
                // gemelde terugbetaling blijft de betaling staan. Zie PspStatusResolver.
                $newPaymentStatus = PspStatusResolver::apply($orderPayment, $order, false);

                if ($newPaymentStatus !== null) {
                    $order->changeStatus($newPaymentStatus);
                    $order->sendGAEcommerceHit();
                }
            }
        } catch (LockTimeoutException $e) {
            return 'timeout exception';
        } finally {
            $lock->release();
        }

        if ($order->status == 'pending') {
            return redirect('/')->with('error', Translation::get('order-status-pending', 'checkout', 'Your order is still pending'));
        }

        if ($order->status == 'cancelled') {
            // Geannuleerd of afgewezen in het betaalscherm van de PSP, welke
            // dat ook was: terug naar de checkout, zie ShoppingCart.
            return ShoppingCart::cancelledPaymentRedirect($order);
        }

        $this->order = $order;
        $this->orderProducts = $order->orderProducts;
        $this->notes = $order->publicLogs;

        foreach (ecommerce()->builder('customOrderFields') as $key => $field) {
            $key = str($key)->snake()->toString();

            if ($order->$key) {
                $this->customOrderFields[$key] = [
                    'label' => $field['label'],
                    'value' => $order->$key,
                ];
            }
        }

        // De aankoop-events (GTM purchase, Meta pixel, TikTok) maar één keer per
        // order: niet opnieuw bij herladen, de terugknop of de link in de
        // bevestigingsmail. De update is atomair, dus ook twee tabbladen
        // tegelijk meten maar één conversie.
        if ($this->order->isPaidFor() && $this->claimPurchaseTracking()) {
            $itemLoop = 0;
            $items = [];

            foreach ($this->order->orderProducts as $orderProduct) {
                $items[] = [
                    'item_id' => $orderProduct->product->id ?? $orderProduct->id,
                    'item_name' => $orderProduct->name,
                    'index' => $itemLoop,
                    'discount' => $orderProduct->discount > 0 ? number_format($orderProduct->discount, 2, '.', '') : 0,
                    'item_category' => ($orderProduct->product && $orderProduct->product->productCategories->count()) ? $orderProduct->product->productCategories()->first()->name : '',
                    'price' => number_format($orderProduct->price / $orderProduct->quantity, 2, '.', ''),
                    'quantity' => $orderProduct->quantity,
                    'ean' => $orderProduct->product ? $orderProduct->product->ean : '',
                ];
                $itemLoop++;
            }

            $countryCode = $this->order->countryCode;
            $phoneE164 = PhoneNormalizer::toE164($this->order->phone_number, $countryCode);

            $userData = [
                'email' => $this->order->email,
                'phone_number' => $phoneE164,
                'address' => [
                    'first_name' => $this->order->first_name,
                    'last_name' => $this->order->last_name,
                    'street' => trim(($this->order->street ?? '') . ' ' . ($this->order->house_nr ?? '')),
                    'city' => $this->order->city,
                    // Provincie/regio wordt niet op de order opgeslagen.
                    'region' => '',
                    'postal_code' => $this->order->zip_code,
                    'country' => $countryCode,
                ],
            ];

            $this->dispatch('orderPaid', array_merge(MetaBrowserPayload::forOrder($this->order), [
                'orderId' => $this->order->id,
                'total' => number_format($this->order->total, 2, '.', ''),
                'discountCode' => $this->order->discountCode ? $this->order->discountCode->code : '',
                'tax' => number_format($this->order->btw, 2, '.', ''),
                'items' => $items,
                'newCustomer' => Order::isPaid()->where('email', $this->order->email)->count() === 1,
                'email' => $this->order->email,
                'phoneNumber' => $phoneE164,
                'country' => $this->order->country,
                'countryCode' => $countryCode,
                'userData' => $userData,
                'estimatedDeliveryDate' => $this->order->created_at->addDays(1)->format('Y-m-d'),
                'tiktokItems' => Customsetting::get('trigger_tiktok_events') ? TikTokHelper::getShoppingCartItems($this->order->total, $this->order->email, $this->order->phone_number) : [],
            ]));
        }
    }

    private function claimPurchaseTracking(): bool
    {
        return Order::whereKey($this->order->id)
            ->whereNull('purchase_tracked_at')
            ->update(['purchase_tracked_at' => now()]) === 1;
    }

    public function render()
    {
        return view(config('dashed-core.site_theme', 'dashed') . '.orders.view-order');
    }
}
