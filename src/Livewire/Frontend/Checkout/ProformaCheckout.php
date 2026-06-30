<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Checkout;

use Livewire\Component;
use Illuminate\Support\Str;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Services\Payments\PaymentTransactionStarter;

class ProformaCheckout extends Component
{
    public string $orderHash = '';
    public ?Order $order = null;

    public string $firstName = '';
    public string $lastName = '';
    public string $email = '';
    public string $phoneNumber = '';
    public string $street = '';
    public string $houseNr = '';
    public string $zipCode = '';
    public string $city = '';
    public string $country = 'NL';
    public string $companyName = '';
    public string $btwId = '';

    public bool $shippingEnabled = false;
    public array $shippingMethods = [];
    public ?string $shippingMethod = null;

    public array $paymentProviders = [];
    public ?string $psp = null;

    public function mount(string $orderHash): void
    {
        $this->order = Order::where('hash', $orderHash)
            ->where('is_proforma', true)
            ->firstOrFail();

        $this->orderHash = $orderHash;
        $this->email = (string) $this->order->email;
        $this->firstName = (string) $this->order->first_name;
        $this->lastName = (string) $this->order->last_name;
        $this->phoneNumber = (string) $this->order->phone_number;
        $this->street = (string) $this->order->street;
        $this->houseNr = (string) $this->order->house_nr;
        $this->zipCode = (string) $this->order->zip_code;
        $this->city = (string) $this->order->city;
        $this->country = $this->order->country ?: 'NL';
        $this->companyName = (string) $this->order->company_name;
        $this->btwId = (string) $this->order->btw_id;
        $this->shippingEnabled = (bool) $this->order->proforma_allow_shipping;

        $this->loadPaymentProviders();

        if ($this->shippingEnabled) {
            $this->retrieveShippingMethods();
        }
    }

    public function rules(): array
    {
        // Spiegelt de adres-regels uit Checkout::rules().
        return [
            'firstName' => ['required', 'max:255'],
            'lastName' => ['required', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phoneNumber' => ['nullable', 'max:255'],
            'street' => ['required', 'max:255'],
            'houseNr' => ['required', 'max:255'],
            'zipCode' => ['required', 'max:10'],
            'city' => ['required', 'max:255'],
            'country' => ['required', 'max:255'],
            'companyName' => ['nullable', 'max:255'],
            'btwId' => ['nullable', 'max:255'],
            'shippingMethod' => [$this->shippingEnabled ? 'required' : 'nullable', 'max:255'],
            'psp' => ['nullable', 'max:255'],
        ];
    }

    public function loadPaymentProviders(): void
    {
        $providers = [];
        foreach (ecommerce()->builder('paymentServiceProviders') ?: [] as $pspId => $psp) {
            if ($psp['class']::isConnected()) {
                $providers[$pspId] = $psp['name'] ?? $pspId;
            }
        }
        $this->paymentProviders = $providers;

        if (! $this->psp && count($providers)) {
            $this->psp = (string) array_key_first($providers);
        }
    }

    public function retrieveShippingMethods(): void
    {
        // Een proforma heeft GEEN sessie-cart (maatwerkproducten mogen daar nooit
        // in komen). De min/max-order-value filtering moet daarom tegen de eigen
        // proforma-total draaien, niet tegen een lege cart. We geven die total
        // expliciet mee via $orderTotalOverride zodat ShoppingCart cartless werkt.
        $proformaTotal = (float) ($this->order->total ?? 0);

        $methods = $this->country
            ? ShoppingCart::getAvailableShippingMethods($this->country, '', null, $proformaTotal)
            : [];

        // getAvailableShippingMethods geeft een Collection terug wanneer een zone
        // matcht en een lege array wanneer niet; collect() normaliseert beide.
        $this->shippingMethods = collect($methods)->values()->toArray();

        if (! $this->shippingMethod && count($this->shippingMethods)) {
            $this->shippingMethod = (string) ($this->shippingMethods[0]['id'] ?? '');
        }
    }

    public function updatedCountry(): void
    {
        if ($this->shippingEnabled) {
            $this->retrieveShippingMethods();
        }
    }

    public function submit()
    {
        $this->validate();

        $order = $this->order;

        $order->first_name = $this->firstName;
        $order->last_name = $this->lastName;
        $order->email = $this->email;
        $order->phone_number = $this->phoneNumber;
        $order->street = $this->street;
        $order->house_nr = $this->houseNr;
        $order->zip_code = $this->zipCode;
        $order->city = $this->city;
        $order->country = $this->country;
        $order->company_name = $this->companyName;
        $order->btw_id = $this->btwId;
        $order->invoice_id = 'PROFORMA';
        $order->status = 'pending';

        $selectedShipping = null;
        if ($this->shippingEnabled && $this->shippingMethod) {
            foreach ($this->shippingMethods as $method) {
                if ((string) ($method['id'] ?? '') === (string) $this->shippingMethod) {
                    $selectedShipping = $method;

                    break;
                }
            }

            if ($selectedShipping) {
                $order->shipping_method_id = $selectedShipping['id'];
            }
        }

        // Verzendkosten in het ordertotaal verrekenen VOOR het opslaan en voor de
        // betaling. outstandingAmount() = max(0, total - paid), dus zonder deze
        // ophoging zou de klant de verzendkosten niet betalen. We spiegelen de
        // manier waarop ConceptOrderService::saveAsConcept de bedragen zet: de
        // (btw-inclusieve) prijs telt op bij total en subtotal, het btw-deel bij btw.
        $shippingCost = 0.0;
        $shippingVatRate = 21.0;
        if ($selectedShipping && ($selectedShipping['costs'] ?? 0) > 0) {
            $shippingCost = (float) $selectedShipping['costs'];
            $shippingVatRate = (float) ($selectedShipping['vat_rate'] ?? 21);

            $shippingVat = $shippingVatRate > 0
                ? $shippingCost - ($shippingCost / (1 + $shippingVatRate / 100))
                : 0.0;

            $order->total = (float) $order->total + $shippingCost;
            $order->subtotal = (float) $order->subtotal + $shippingCost;
            $order->btw = round((float) $order->btw + $shippingVat, 2);
        }

        $order->save();
        $order->refresh();

        // Verzendkosten als losse regel (product_id = null), net als Checkout::submit().
        if ($shippingCost > 0) {
            $orderProduct = new OrderProduct();
            $orderProduct->quantity = 1;
            $orderProduct->product_id = null;
            $orderProduct->order_id = $order->id;
            $orderProduct->name = $selectedShipping['correctName'] ?? ($selectedShipping['name'] ?? 'Verzendkosten');
            $orderProduct->price = $shippingCost;
            $orderProduct->vat_rate = $shippingVatRate;
            $orderProduct->discount = 0;
            $orderProduct->product_extras = [];
            $orderProduct->sku = 'shipping_costs';
            $orderProduct->save();
        }

        $order->createInvoice();
        $order->refresh();

        // Betaling starten: spiegelt RemainderPaymentController. De klant kiest
        // de PSP; valt terug op de eerste verbonden provider.
        $providerId = null;
        $providerClass = null;
        foreach (ecommerce()->builder('paymentServiceProviders') ?: [] as $pspId => $psp) {
            if (! $psp['class']::isConnected()) {
                continue;
            }
            if ($this->psp && $this->psp === (string) $pspId) {
                $providerId = $pspId;
                $providerClass = $psp['class'];

                break;
            }
            if (! $providerId) {
                $providerId = $pspId;
                $providerClass = $psp['class'];
            }
        }

        if (! $providerClass) {
            session()->flash('error', 'Er is geen betaalprovider beschikbaar.');

            return;
        }

        $orderPayment = new OrderPayment();
        $orderPayment->order_id = $order->id;
        $orderPayment->amount = $order->outstandingAmount();
        $orderPayment->status = 'pending';
        $orderPayment->psp = $providerId;
        $orderPayment->hash = (string) Str::uuid();
        $orderPayment->save();
        $orderPayment->refresh();

        try {
            $transaction = PaymentTransactionStarter::start($orderPayment, PaymentTransactionStarter::CONTEXT_REMAINDER_PAYMENT);
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', 'De betaling kon niet gestart worden.');

            return;
        }

        if (! empty($transaction['redirectUrl'])) {
            return redirect()->away($transaction['redirectUrl']);
        }

        session()->flash('error', 'De betaling kon niet gestart worden.');
    }

    public function render()
    {
        return view('dashed-ecommerce-core::livewire.frontend.checkout.proforma-checkout');
    }
}
