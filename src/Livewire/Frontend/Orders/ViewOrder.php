<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Orders;

use Livewire\Component;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Classes\TikTokHelper;
use Illuminate\Contracts\Cache\LockTimeoutException;

class ViewOrder extends Component
{
    public Order $order;
    public Collection $orderProducts;
    public Collection $notes;
    public array $customOrderFields = [];

    public function mount()
    {
        $possibleIdValues = [
            'orderId',
            'order_id',
            'paymentId',
            'id',
            'transactionid',
        ];

        $orderPayment = null;

        foreach ($possibleIdValues as $possibleIdValue) {
            if (! $orderPayment) {
                $paymentId = request()->get($possibleIdValue);
                if ($paymentId) {
                    $orderPayment = OrderPayment::where('psp_id', $paymentId)->orWhere('hash', $paymentId)->first();
                }
            }
        }

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
                foreach (ecommerce()->builder('paymentServiceProviders') ?: [] as $pspId => $psp) {
                    if ($orderPayment->psp == $pspId) {
                        $newStatus = $psp['class']::getOrderStatus($orderPayment);
                        $newPaymentStatus = $orderPayment->changeStatus($newStatus);
                    }
                }

                if (isset($newPaymentStatus)) {
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
            return redirect('/')->with('error', Translation::get('order-status-cancelled', 'checkout', 'Your order is cancelled'));
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

        if ($this->order->isPaidFor()) {
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
            $phoneE164 = $this->normalizePhoneToE164($this->order->phone_number, $countryCode);

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

            $this->dispatch('orderPaid', [
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
            ]);
        }
    }

    public function render()
    {
        return view(config('dashed-core.site_theme', 'dashed') . '.orders.view-order');
    }

    /**
     * Best-effort normalisatie naar E.164 (+landcode...). Zonder
     * libphonenumber dekken we de gangbare NL/BE-invoer af; onbekende
     * landen vallen terug op de opgeschoonde invoer.
     */
    private function normalizePhoneToE164(?string $phone, ?string $countryCode): string
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return '';
        }

        // Internationale prefix 00 -> +
        if (str_starts_with($phone, '00')) {
            $phone = '+' . substr($phone, 2);
        }

        // Al in E.164: alleen cijfers achter de + behouden.
        if (str_starts_with($phone, '+')) {
            return '+' . preg_replace('/\D/', '', substr($phone, 1));
        }

        $digits = preg_replace('/\D/', '', $phone);
        if ($digits === '') {
            return '';
        }

        $callingCodes = [
            'NL' => '31',
            'BE' => '32',
            'DE' => '49',
            'FR' => '33',
            'LU' => '352',
        ];
        $callingCode = $callingCodes[strtoupper((string) $countryCode)] ?? null;

        if ($callingCode === null) {
            // Onbekend land: geef de cijfers terug zonder te gokken op een landcode.
            return $digits;
        }

        // Nationale notatie met voorloop-0 -> landcode ervoor.
        if (str_starts_with($digits, '0')) {
            return '+' . $callingCode . substr($digits, 1);
        }

        // Begint al met de landcode.
        if (str_starts_with($digits, $callingCode)) {
            return '+' . $digits;
        }

        return '+' . $callingCode . $digits;
    }
}
