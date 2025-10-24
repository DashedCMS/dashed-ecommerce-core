<?php

namespace Dashed\DashedEcommerceCore\Models;

use Exception;
use Illuminate\Support\Str;
use Dashed\DashedCore\Models\User;
use Spatie\Activitylog\LogOptions;
use Illuminate\Support\Facades\App;
use Dashed\DashedCore\Classes\Mails;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Dashed\ReceiptPrinter\ReceiptPrinter;
use Dashed\DashedCore\Models\Customsetting;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dashed\DashedEcommerceCore\Classes\Orders;
use Dashed\DashedCore\Traits\HasDynamicRelation;
use Dashed\DashedEcommerceCore\Classes\Printing;
use Dashed\DashedEcommerceCore\Classes\Countries;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Jobs\SendInvoiceJob;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Dashed\DashedEcommerceCore\Mail\OrderCancelledMail;
use Dashed\DashedEcommerceCore\Mail\ProductOnLowStockEmail;
use Dashed\DashedEcommerceCore\Mail\AdminOrderCancelledMail;
use Dashed\DashedEcommerceCore\Events\Orders\InvoiceCreatedEvent;
use Dashed\DashedEcommerceCore\Mail\OrderCancelledWithCreditMail;
use Dashed\DashedEcommerceCore\Events\Orders\OrderMarkedAsPaidEvent;
use Dashed\DashedEcommerceCore\Jobs\UpdateProductStockInformationJob;
use Dashed\DashedEcommerceCore\Mail\OrderFulfillmentStatusChangedMail;

class Order extends Model
{
    use SoftDeletes;
    use LogsActivity;
    use HasDynamicRelation;

    protected static $logFillable = true;

    protected $table = 'dashed__orders';

    protected $casts = [
        'vat_percentages' => 'array',
    ];

    protected $appends = [
        'name',
        'invoiceName',
        'paymentMethod',
        'paidAmount',
        'openAmount',
    ];

    protected $with = [
        'orderProducts',
        'orderPayments',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            $order->ip = request()->ip();
            $order->hash = Str::random(32);
            $order->locale = app()->getLocale();
            $order->initials = $order->first_name ? strtoupper($order->first_name[0]) . '.' : '';
            $order->site_id = Sites::getActive();
        });

        static::created(function ($order) {
            if ($order->discountCode && $order->discount > 0) {
                if ($order->discountCode->is_giftcard) {
                    $order->discountCode->reserved_amount = $order->discountCode->reserved_amount + $order->discount;
                    $order->discountCode->discount_amount = $order->discountCode->discount_amount - $order->discount;
                    $order->discountCode->save();

                    $order->discountCode->createLog(tag: 'giftcard.order.transaction.started', newAmount: $order->discountCode->discount_amount, oldAmount: $order->discountCode->discount_amount + $order->discount, orderId: $order->id);
                }
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public function getNameAttribute(): string
    {
        if ($this->first_name || $this->last_name) {
            if ($this->first_name && $this->last_name) {
                return "$this->first_name $this->last_name";
            } elseif ($this->first_name) {
                return $this->first_name;
            } else {
                return $this->last_name;
            }
        } else {
            return $this->email ?: 'Geen naam';
        }
    }

    public function getInvoiceNameAttribute(): string
    {
        if ($this->invoice_first_name || $this->invoice_last_name) {
            if ($this->invoice_first_name && $this->invoice_last_name) {
                return "$this->invoice_first_name $this->invoice_last_name";
            } elseif ($this->invoice_first_name) {
                return $this->invoice_first_name;
            } else {
                return $this->invoice_last_name;
            }
        } else {
            return $this->name;
        }
    }

    public function getPspIdAttribute(): ?string
    {
        return $this->orderPayments()->first() ? $this->orderPayments()->first()->psp_id : '';
    }

    public function getPaymentMethodAttribute(): string
    {
        return $this->mainPaymentMethod ? $this->mainPaymentMethod->name : ($this->orderPayments()->first() ? $this->orderPayments()->first()->payment_method_name : Translation::get('no-payment-method-available', 'orders', 'Geen methode beschikbaar'));
    }

    public function getPaymentMethodInstructionsAttribute(): string
    {
        return $this->orderPayments()->first() ? $this->orderPayments()->first()->paymentMethodInstructions : '';
    }

    public function getPaidAmountAttribute()
    {
        return $this->orderPayments()->where('status', 'paid')->sum('amount');
    }

    public function getOpenAmountAttribute()
    {
        return $this->getRawOriginal('total') - $this->paidAmount;
    }

    public function getStatusLabelsAttribute(): array
    {
        $labels = [];

        if ($this->contains_pre_orders) {
            $labels[] = [
                'status' => 'Bevat pre-orders',
                'color' => 'warning',
            ];
        }

        return $labels;
    }

    public function orderProducts(): HasMany
    {
        return $this->hasMany(OrderProduct::class)->withTrashed();
    }

    public function orderProductsWithProduct(): HasMany
    {
        return $this->hasMany(OrderProduct::class)->whereNotNull('product_id')->withTrashed();
    }

    public function orderPayments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class);
    }

    public function parentCreditOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'credit_for_order_id');
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class)
            ->withTrashed();
    }

    public function mainPaymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id')
            ->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(OrderLog::class);
    }

    public function trackAndTraces(): HasMany
    {
        return $this->hasMany(OrderTrackAndTrace::class);
    }

    public function publicLogs(): HasMany
    {
        return $this->hasMany(OrderLog::class)
            ->where('public_for_customer', 1);
    }

    public function creditOrders()
    {
        return $this->hasMany(self::class, 'credit_for_order_id');
    }

    //    public function montaPortalOrder()
    //    {
    //        return $this->hasOne(MontaportalOrder::class);
    //    }

    //    public function exactonlineOrder()
    //    {
    //        return $this->hasOne(ExactonlineOrder::class);
    //    }

    //    public function eboekhoudenOrderConnection()
    //    {
    //        return $this->belongsTo(EboekhoudenOrderConnection::class);
    //    }

    public function isPaidFor(): bool
    {
        if ($this->status == 'paid' || $this->status == 'partially_paid' || $this->status == 'waiting_for_confirmation') {
            return true;
        }

        return false;
    }

    public function isReturnStatus(): bool
    {
        if ($this->status == 'return') {
            return true;
        }

        return false;
    }

    public function scopeIsPaid($query)
    {
        return $query->whereIn('status', ['paid', 'waiting_for_confirmation', 'partially_paid']);
    }

    public function scopeIsReturn($query)
    {
        return $query->whereIn('status', ['return']);
    }

    public function scopeIsPaidOrReturn($query)
    {
        return $query->whereIn('status', ['paid', 'waiting_for_confirmation', 'partially_paid', 'return']);
    }

    public function scopeThisSite($query)
    {
        return $query->where('site_id', Sites::getActive());
    }

    public function scopeCalculatableForStats($query)
    {
        return $query->whereNotIn('invoice_id', ['PROFORMA', 'RETURN']);
        //        return $query->whereNotIn('invoice_id', ['PROFORMA', 'RETURN'])->whereIn('order_origin', ['own', 'pos']);
    }

    public function scopeUnhandled($query)
    {
        return $query->where('fulfillment_status', 'unhandled')->isPaid();
    }

    public function scopeNotHandled($query)
    {
        return $query->where('fulfillment_status', '!=', 'handled')->isPaid();
    }

    //    public function scopePushableToEfulfillmentShop($query)
    //    {
    //        return $query->where('pushable_to_efulfillment_shop', 1)->where('pushed_to_efulfillment_shop', 0)->isPaid()->thisSite();
    //    }
    //
    //    public function scopePushedToEfulfillmentShop($query)
    //    {
    //        return $query->where('pushable_to_efulfillment_shop', 1)->where('pushed_to_efulfillment_shop', 1)->isPaid()->thisSite();
    //    }
    //
    //    public function scopePushableToEboekhouden($query)
    //    {
    //        return $query->where('pushable_to_eboekhouden', 1)->where('pushed_to_eboekhouden', 0);
    //    }

    public function scopeSearch($query, $search = null)
    {
        $search = strtolower($search ?: request()->get('search'));
        if ($search) {
            $query->where('hash', 'LIKE', "%$search%")
                ->orWhere('id', 'LIKE', "%$search%")
                ->orWhere('ip', 'LIKE', "%$search%")
                ->orWhere('first_name', 'LIKE', "%$search%")
                ->orWhere('last_name', 'LIKE', "%$search%")
                ->orWhere('email', 'LIKE', "%$search%")
                ->orWhere('street', 'LIKE', "%$search%")
                ->orWhere('house_nr', 'LIKE', "%$search%")
                ->orWhere('zip_code', 'LIKE', "%$search%")
                ->orWhere('city', 'LIKE', "%$search%")
                ->orWhere('country', 'LIKE', "%$search%")
                ->orWhere('company_name', 'LIKE', "%$search%")
                ->orWhere('btw_id', 'LIKE', "%$search%")
                ->orWhere('note', 'LIKE', "%$search%")
                ->orWhere('invoice_first_name', 'LIKE', "%$search%")
                ->orWhere('invoice_last_name', 'LIKE', "%$search%")
                ->orWhere('invoice_street', 'LIKE', "%$search%")
                ->orWhere('invoice_house_nr', 'LIKE', "%$search%")
                ->orWhere('invoice_zip_code', 'LIKE', "%$search%")
                ->orWhere('invoice_city', 'LIKE', "%$search%")
                ->orWhere('invoice_country', 'LIKE', "%$search%")
                ->orWhere('invoice_id', 'LIKE', "%$search%")
                ->orWhere('status', 'LIKE', "%$search%");
        }
    }

    public function scopeQuickSearch($query, $search)
    {
        $search = strtolower($search ?: request()->get('search'));
        if ($search) {
            $query->Where('id', 'LIKE', "%$search%")
                ->orWhere('first_name', 'LIKE', "%$search%")
                ->orWhere('last_name', 'LIKE', "%$search%")
                ->orWhere('email', 'LIKE', "%$search%")
                ->orWhere('street', 'LIKE', "%$search%")
                ->orWhere('house_nr', 'LIKE', "%$search%")
                ->orWhere('city', 'LIKE', "%$search%")
                ->orWhere('country', 'LIKE', "%$search%")
                ->orWhere('company_name', 'LIKE', "%$search%")
                ->orWhere('status', 'LIKE', "%$search%")
                ->orWhere('invoice_id', 'LIKE', "%$search%")
                ->orWhere('id', 'LIKE', "%$search%");
        }
    }

    public function getCountryIsoCodeAttribute()
    {
        return Countries::getCountryIsoCode($this->country);
    }

    public function labelPrinted()
    {
        //        if ($this->keen_delivery_label_printed) {
        //            return true;
        //        }

        return false;
    }

    public function generateInvoiceId()
    {
        if (($this->invoice_id == 'PROFORMA' || $this->invoice_id == 'RETURN')) {
            //        if (in_array($this->order_origin, ['own', 'pos']) && ($this->invoice_id == 'PROFORMA' || $this->invoice_id == 'RETURN')) {
            if (Customsetting::get('random_invoice_number')) {
                $invoiceNumber = '';
                foreach (str_split(Customsetting::get('invoice_id_replacement', config('dashed.currentSite'), '*****')) as $codeCharacter) {
                    if ($codeCharacter == '*') {
                        $codeCharacter = strtoupper(Str::random(1));
                    }
                    $invoiceNumber .= $codeCharacter;
                }

                $invoiceId = Customsetting::get('invoice_id_prefix') . $invoiceNumber . Customsetting::get('invoice_id_suffix');
                while (Order::where('invoice_id', $invoiceId)->count()) {
                    $invoiceNumber = '';
                    foreach (str_split(Customsetting::get('invoice_id_replacement', config('dashed.currentSite'), '*****')) as $codeCharacter) {
                        if ($codeCharacter == '*') {
                            $codeCharacter = strtoupper(Str::random(1));
                        }
                        $invoiceNumber .= $codeCharacter;
                    }

                    $invoiceId = Customsetting::get('invoice_id_prefix') . $invoiceNumber . Customsetting::get('invoice_id_suffix');
                }
            } else {
                $invoiceNumber = Customsetting::get('current_invoice_number', config('dashed.currentSite'), 1000) + 1;
                $invoiceId = Customsetting::get('invoice_id_prefix') . $invoiceNumber . Customsetting::get('invoice_id_suffix');
                Customsetting::set('current_invoice_number', $invoiceNumber);
            }
            $this->invoice_id = $invoiceId;
            $this->save();
        }
    }

    public function createInvoice()
    {
        //        if (in_array($this->order_origin, ['own', 'pos'])) {
        if ($this->parentCreditOrder) {
            //                OrderLog::createLog(orderId: $this->id, note: 'Creating credit invoice', isDebugLog: true);
            $this->createCreditInvoice();
        } elseif ($this->status == 'paid' || $this->status == 'waiting_for_confirmation' || $this->status == 'partially_paid') {
            //                OrderLog::createLog(orderId: $this->id, note: 'Creating normal invoice', isDebugLog: true);
            $this->createNormalInvoice();
        } else {
            //                OrderLog::createLog(orderId: $this->id, note: 'Creating no invoice, WRONG', isDebugLog: true);
        }
        //        }
    }

    public function createNormalInvoice()
    {
        //        if (in_array($this->order_origin, ['own', 'pos'])) {
        //            OrderLog::createLog(orderId: $this->id, note: 'Generating invoice id', isDebugLog: true);
        $this->generateInvoiceId();
        //            OrderLog::createLog(orderId: $this->id, note: 'Generating invoice id done', isDebugLog: true);
        //            OrderLog::createLog(orderId: $this->id, note: 'Retrieving order again', isDebugLog: true);
        $order = Order::find($this->id);
        //            OrderLog::createLog(orderId: $this->id, note: 'Retrieving order again done', isDebugLog: true);
        $invoicePath = '/dashed/invoices/invoice-' . $order->invoice_id . '-' . $order->hash . '.pdf';
        if (!Storage::disk('dashed')->exists($invoicePath)) {
            //                OrderLog::createLog(orderId: $this->id, note: 'Invoice does not exists yet, creating view', isDebugLog: true);
            $view = View::make('dashed-ecommerce-core::invoices.invoice', [
                'order' => $order,
            ]);
            OrderLog::createLog(orderId: $this->id, note: 'Rendering view', isDebugLog: true);
            $contents = $view->render();
            OrderLog::createLog(orderId: $this->id, note: 'Creating PDF app', isDebugLog: true);
            $pdf = App::make('dompdf.wrapper');
            OrderLog::createLog(orderId: $this->id, note: 'Loading HTML', isDebugLog: true);
            $pdf->loadHTML($contents);
            OrderLog::createLog(orderId: $this->id, note: 'Getting the output', isDebugLog: true);

            try {
                $output = $pdf->output();
            } catch (\Exception $e) {
                OrderLog::createLog(orderId: $this->id, note: 'Error: ' . $e->getMessage(), isDebugLog: true);

                throw new Exception($e->getMessage());
            }
            OrderLog::createLog(orderId: $this->id, note: 'Output retrieved', isDebugLog: true);

            //                OrderLog::createLog(orderId: $this->id, note: 'Put on disk', isDebugLog: true);
            Storage::disk('dashed')->put($invoicePath, $output);
            //                OrderLog::createLog(orderId: $this->id, note: 'Put on disk done', isDebugLog: true);

            //                OrderLog::createLog(orderId: $this->id, note: 'Dispatch InvoiceCreatedEvent', isDebugLog: true);
            InvoiceCreatedEvent::dispatch($this);
            //                OrderLog::createLog(orderId: $this->id, note: 'Dispatch InvoiceCreatedEvent done', isDebugLog: true);
        }

        if (!$this->invoice_send_to_customer) {
            //                OrderLog::createLog(orderId: $this->id, note: 'Dispatch SendInvoiceJob', isDebugLog: true);
            SendInvoiceJob::dispatch($this, auth()->check() ? auth()->user() : null);
            //                OrderLog::createLog(orderId: $this->id, note: 'Dispatch SendInvoiceJob done', isDebugLog: true);
        }
        //        }
    }

    public function createPackingSlip()
    {
        if ($this->status == 'paid' || $this->status == 'waiting_for_confirmation' || $this->status == 'partially_paid' || $this->parentCreditOrder) {
            $order = Order::find($this->id);
            if (!Storage::disk('dashed')->exists('/packing-slips/packing-slip-' . ($order->invoice_id ?: $order->id) . '-' . $order->hash . '.pdf')) {
                $view = View::make('dashed-ecommerce-core::invoices.packing-slip', compact('order'));
                $contents = $view->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($contents);
                $output = $pdf->output();

                $invoicePath = '/dashed/packing-slips/packing-slip-' . ($order->invoice_id ?: $order->id) . '-' . $order->hash . '.pdf';
                Storage::disk('dashed')->put($invoicePath, $output);
            }
        }
    }

    public function createCreditInvoice()
    {
        if (($this->status == 'paid' || $this->status == 'waiting_for_confirmation' || $this->status == 'partially_paid' || $this->parentCreditOrder)) {
            //        if (in_array($this->order_origin, ['own', 'pos']) && ($this->status == 'paid' || $this->status == 'waiting_for_confirmation' || $this->status == 'partially_paid' || $this->parentCreditOrder)) {
            $this->generateInvoiceId();
            $order = $this;
            if (!Storage::disk('dashed')->exists('/invoices/invoice-' . $order->invoice_id . '-' . $order->hash . '.pdf')) {
                $view = View::make('dashed-ecommerce-core::invoices.invoice', compact('order'));
                $contents = $view->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($contents);
                $output = $pdf->output();

                $invoicePath = '/dashed/invoices/invoice-' . $order->invoice_id . '-' . $order->hash . '.pdf';
                Storage::disk('dashed')->put($invoicePath, $output);

                InvoiceCreatedEvent::dispatch($this);
            }
        }
    }

    public function deductStock()
    {
        foreach ($this->orderProducts as $orderProduct) {
            if ($orderProduct->product) {
                if ($orderProduct->product->use_stock) {
                    $orderProduct->product->stock = $orderProduct->product->stock - $orderProduct->quantity;
                }
                $orderProduct->product->purchases = $orderProduct->product->purchases + $orderProduct->quantity;
                $orderProduct->product->save();
                if ($orderProduct->product->parent && $orderProduct->product->parent->use_parent_stock) {
                    if ($orderProduct->product->parent->use_stock) {
                        $orderProduct->product->parent->stock = $orderProduct->product->parent->stock - $orderProduct->quantity;
                    }
                    $orderProduct->product->parent->save();
                }
            }
        }

        foreach (Product::whereIn('id', $this->orderProducts->pluck('product_id'))->get() as $product) {
            if ($product->low_stock_notification && $product->use_stock && $product->stock() < $product->low_stock_notification_limit) {
                try {
                    foreach (Mails::getAdminLowStockNotificationEmails() as $lowStockNotificationEmail) {
                        Mail::to($lowStockNotificationEmail)->send(new ProductOnLowStockEmail($product));
                    }
                } catch (\Exception $e) {
                }
            }
        }
    }

    public function deductDiscount()
    {
        if ($this->discountCode) {
            if ($this->discountCode->use_stock) {
                $this->discountCode->stock = $this->discountCode->stock - 1;
            }
            $this->discountCode->stock_used = $this->discountCode->stock_used + 1;
            $this->discountCode->save();

            if ($this->discountCode->is_giftcard) {
                $this->discountCode->used_amount = $this->discountCode->used_amount + $this->discount;
                $this->discountCode->reserved_amount = $this->discountCode->reserved_amount - $this->discount;
                $this->discountCode->save();
                $this->discountCode->createLog(tag: 'giftcard.order.transaction.completed', orderId: $this->id, oldAmount: $this->discountCode->discount_amount, newAmount: $this->discountCode->discount_amount);
            }
        }
    }

    public function refillStock($refillPurchases = true)
    {
        foreach ($this->orderProducts as $orderProduct) {
            if ($orderProduct->product) {
                if ($orderProduct->product->use_stock) {
                    if ($orderProduct->quantity < 0) {
                        $orderProduct->product->stock = $orderProduct->product->stock - $orderProduct->quantity;
                    } else {
                        $orderProduct->product->stock = $orderProduct->product->stock + $orderProduct->quantity;
                    }
                }
                if ($refillPurchases) {
                    if ($orderProduct->quantity < 0) {
                        $orderProduct->product->purchases = $orderProduct->product->purchases + $orderProduct->quantity;
                    } else {
                        $orderProduct->product->purchases = $orderProduct->product->purchases - $orderProduct->quantity;
                    }
                }
                $orderProduct->product->save();
                if ($orderProduct->product->parent && $orderProduct->product->parent->use_parent_stock) {
                    if ($orderProduct->product->parent->use_stock) {
                        if ($orderProduct->product->quantity < 0) {
                            $orderProduct->product->parent->stock = $orderProduct->product->parent->stock - $orderProduct->quantity;
                        } else {
                            $orderProduct->product->parent->stock = $orderProduct->product->parent->stock + $orderProduct->quantity;
                        }
                        $orderProduct->product->parent->save();
                    }
                }
            }
        }
    }

    public function refillDiscount()
    {
        if ($this->discountCode) {
            if ($this->discountCode->use_stock) {
                $this->discountCode->stock = $this->discountCode->stock + 1;
            }
            $this->discountCode->stock_used = $this->discountCode->stock_used - 1;
            $this->discountCode->save();

            $this->refillGiftcard();
        }
    }

    public function refillGiftcard()
    {
        if ($this->discountCode && $this->discountCode->is_giftcard) {
            $this->discountCode->discount_amount = $this->discountCode->discount_amount + $this->discount;
            $this->discountCode->reserved_amount = $this->discountCode->reserved_amount - $this->discount;
            $this->discountCode->save();
            $this->discountCode->createLog(tag: 'giftcard.order.transaction.cancelled', orderId: $this->id, oldAmount: $this->discountCode->discount_amount - $this->discount, newAmount: $this->discountCode->discount_amount);
        }
    }

    public function refillGiftcardFromPaidOrder()
    {
        if ($this->discountCode && $this->discountCode->is_giftcard) {
            $this->discountCode->discount_amount = $this->discountCode->discount_amount + $this->discount;
            $this->discountCode->save();
            $this->discountCode->createLog(tag: 'giftcard.order.transaction.cancelled', orderId: $this->id, oldAmount: $this->discountCode->discount_amount - $this->discount, newAmount: $this->discountCode->discount_amount);
        }
    }

    public function updateOrderProductsProductInformation()
    {
        foreach ($this->orderProducts as $orderProduct) {
            if ($orderProduct->product) {
                UpdateProductStockInformationJob::dispatch($orderProduct->product)->onQueue('ecommerce');
            }
        }
    }

    public function changeStatus($newStatus = null, $sendMail = false)
    {
        //        Cache::lock('order.updateStatus.' . $this->id, 10)
        //            ->block(15, function () use ($newStatus) {
        if ($newStatus && $this->status != $newStatus) {
            match ($newStatus) {
                'paid' => $this->markAsPaid(),
                'partially_paid' => $this->markAsPartiallyPaid(),
                'cancelled' => $this->markAsCancelled(),
                'waiting_for_confirmation' => $this->markAsWaitingForConfirmation(),
            };
        }
        //            });
    }

    public function changeFulfillmentStatus($newStatus)
    {
        if ($this->fulfillment_status == $newStatus) {
            return;
        }

        $this->fulfillment_status = $newStatus;
        $this->save();
        if ($this->isPaidFor()) {
            foreach (Orders::getFulfillmentStatusses() as $key => $fulfillmentStatus) {
                if ($this->fulfillment_status == $key && Customsetting::get("fulfillment_status_{$key}_enabled", null, false, $this->locale)) {
                    try {
                        Mail::to($this->email)->send(new OrderFulfillmentStatusChangedMail($this, Customsetting::get("fulfillment_status_{$key}_email_subject", null, null, $this->locale), Customsetting::get("fulfillment_status_{$key}_email_content", null, null, $this->locale)));
                        OrderLog::createLog(orderId: $this->id, tag: "order.fulfillment-status-update-to-{$key}.mail.send");
                    } catch (\Exception $e) {
                        OrderLog::createLog(orderId: $this->id, tag: "order.fulfillment-status-update-to-{$key}.mail.not-send");
                    }
                }
            }
        }
    }

    public function markAsPaid()
    {
        if ($this->status == 'waiting_for_confirmation' || $this->status == 'partially_paid') {
            $this->status = 'paid';
            $this->save();

            OrderLog::createLog(orderId: $this->id, tag: 'order.marked-as-paid');
        } else {
            $this->status = 'paid';
            $this->save();

            if (auth()->check() && auth()->user()->id != $this->user_id) {
                OrderLog::createLog(orderId: $this->id, tag: 'order.marked-as-paid');
            } else {
                //                OrderLog::createLog(orderId: $this->id, note: 'Marked as paid from url ' . url()->current(), isDebugLog: true);

                OrderLog::createLog(orderId: $this->id, tag: 'order.paid');
            }

            OrderLog::createLog(orderId: $this->id, note: 'Creating invoice', isDebugLog: true);

            try {
                $this->createInvoice();
            } catch (\Exception $e) {
                OrderLog::createLog(orderId: $this->id, note: 'Error creating invoice: ' . $e->getMessage(), isDebugLog: true);
            }
            //            OrderLog::createLog(orderId: $this->id, note: 'Invoice created', isDebugLog: true);

            //            OrderLog::createLog(orderId: $this->id, note: 'Deducting stock', isDebugLog: true);
            $this->deductStock();
            //            OrderLog::createLog(orderId: $this->id, note: 'Stock deducted', isDebugLog: true);
            //            OrderLog::createLog(orderId: $this->id, note: 'Discount deducted', isDebugLog: true);
            $this->deductDiscount();
            //            OrderLog::createLog(orderId: $this->id, note: 'Deducted discount', isDebugLog: true);

            $this->sendAutomaticFulfillmentProducts();

            //            OrderLog::createLog(orderId: $this->id, note: 'Mark as paid event dispatch start', isDebugLog: true);
            OrderMarkedAsPaidEvent::dispatch($this);
            //            OrderLog::createLog(orderId: $this->id, note: 'Mark as paid event dispatch end', isDebugLog: true);

            //            OrderLog::createLog(orderId: $this->id, note: 'Emptying shopping cart', isDebugLog: true);
            cartHelper()->emptyCart();
            //            OrderLog::createLog(orderId: $this->id, note: 'Shopping cart emptied', isDebugLog: true);

            $this->sendGAEcommerceHit();
        }

        $this->updateOrderProductsProductInformation();
    }

    public function markAsPartiallyPaid()
    {
        if ($this->status == 'partially_paid') {
            return;
        }

        $this->status = 'partially_paid';
        $this->save();

        OrderLog::createLog(orderId: $this->id, tag: 'order.partially_paid');

        $this->createInvoice();

        $this->deductStock();
        $this->deductDiscount();

        OrderMarkedAsPaidEvent::dispatch($this);

        cartHelper()->emptyCart();

        $this->sendGAEcommerceHit();

        $this->updateOrderProductsProductInformation();
    }

    public function markAsWaitingForConfirmation()
    {
        $this->status = 'waiting_for_confirmation';
        $this->save();

        OrderLog::createLog(orderId: $this->id, tag: 'order.waiting_for_confirmation');

        $this->generateInvoiceId();
        $this->createInvoice();

        $this->deductStock();
        $this->deductDiscount();
        OrderMarkedAsPaidEvent::dispatch($this);

        cartHelper()->emptyCart();

        $this->sendGAEcommerceHit();
        $this->updateOrderProductsProductInformation();
    }

    public function markAsCancelled($sendMail = false)
    {
        if ($this->status == 'paid') {
            $this->refillStock();
            $this->refillDiscount();
        } else {
            $this->refillGiftcard();
        }

        foreach ($this->orderPayments()->where('status', 'pending')->get() as $orderPayment) {
            $orderPayment->changeStatus('cancelled');
        }

        $this->status = 'cancelled';
        $this->changeFulfillmentStatus('handled');
        $this->save();

        OrderLog::createLog(orderId: $this->id, tag: app()->runningInConsole() ? 'order.system.cancelled' : 'order.cancelled');

        if ($sendMail) {
            if (app()->runningInConsole()) {
                try {
                    Mail::to($this->email)->send(new OrderCancelledMail($this));
                    OrderLog::createLog(orderId: $this->id, tag: 'order.system.cancelled.mail.send');
                } catch (\Exception $e) {
                    OrderLog::createLog(orderId: $this->id, tag: 'order.system.cancelled.mail.send.failed', note: 'Error: ' . $e->getMessage());
                }
            } else {
                try {
                    Mail::to($this->email)->send(new OrderCancelledMail($this));
                    OrderLog::createLog(orderId: $this->id, tag: 'order.cancelled.mail.send');
                } catch (\Exception $e) {
                    OrderLog::createLog(orderId: $this->id, tag: 'order.cancelled.mail.send.failed', note: 'Error: ' . $e->getMessage());
                }
            }
        }

        $this->updateOrderProductsProductInformation();
    }

    public function markAsCancelledWithCredit($sendCustomerEmail, $productsMustBeReturned, $restock, $refundDiscountCosts, $extraOrderLineName, $extraOrderLinePrice, $chosenOrderProducts, $fulfillmentStatus, $paymentMethodId)
    {
        $newOrder = $this->replicate();
        $newOrder->invoice_id = 'RETURN';
        $newOrder->total = 0;
        $newOrder->subtotal = 0;
        $newOrder->btw = 0;
        $newOrder->discount = 0;
        $newOrder->status = 'return';
        $newOrder->fulfillment_status = 'waiting_for_return';
        $newOrder->credit_for_order_id = $this->id;
        if ($productsMustBeReturned) {
            $newOrder->retour_status = 'waiting_for_return';
        } else {
            $newOrder->retour_status = 'handled';
        }
        $newOrder->save();

        if (app()->runningInConsole()) {
            OrderLog::createLog(orderId: $newOrder->id, tag: 'order.system.cancelled');
        } else {
            OrderLog::createLog(orderId: $newOrder->id, tag: 'order.cancelled');
        }

        $calculateInclusiveTax = Customsetting::get('taxes_prices_include_taxes');

        $discountToGet = $this->discount;
        $discountToSave = 0;

        $vatPercentages = 0;
        $vatPercentageCount = 0;
        $vatPercentagesForOrder = [];
        $vatPercentagesCount = [];

        foreach ($chosenOrderProducts as $chosenOrderProduct) {
            if ($chosenOrderProduct['refundQuantity'] > 0) {
                $orderProduct = new OrderProduct();
                $orderProduct->quantity = 0 - $chosenOrderProduct['refundQuantity'];
                $orderProduct->product_id = $chosenOrderProduct['product_id'];
                $orderProduct->name = $chosenOrderProduct['name'];
                $orderProduct->price = 0 - (($chosenOrderProduct['price'] / $chosenOrderProduct['quantity']) * $chosenOrderProduct['refundQuantity']);
                $orderProduct->discount = 0 - $chosenOrderProduct['discount'];
                $orderProduct->product_extras = $chosenOrderProduct['product_extras'];
                $orderProduct->sku = $chosenOrderProduct['sku'];
                $orderProduct->vat_rate = $chosenOrderProduct['vat_rate'];
                $orderProduct->order_id = $newOrder->id;
                $orderProduct->save();

                $discountToGet -= $chosenOrderProduct['discount'];
                $discountToSave += $chosenOrderProduct['discount'];
                $vatPercentages += ($orderProduct->vat_rate * $chosenOrderProduct['refundQuantity']);
                $vatPercentageCount += $chosenOrderProduct['refundQuantity'];

                $price = $orderProduct->price;

                $newOrder->total += $price;

                if ($calculateInclusiveTax) {
                    $taxPrice = $price / (100 + $orderProduct->vat_rate) * $orderProduct->vat_rate;
                } else {
                    $taxPrice = $price / 100 * $orderProduct->vat_rate;
                }

                $newOrder->btw += $taxPrice;

                $vatRate = number_format($orderProduct->vat_rate, 0);
                if (!isset($vatPercentagesForOrder[$vatRate])) {
                    $vatPercentagesForOrder[$vatRate] = 0;
                }
                $vatPercentagesForOrder[$vatRate] += number_format($taxPrice, 2);
                if (!isset($vatPercentagesCount[$vatRate])) {
                    $vatPercentagesCount[$vatRate] = 0;
                }
                $vatPercentagesCount[$vatRate] += $chosenOrderProduct['refundQuantity'];
            }
        }

        if ($extraOrderLineName || $extraOrderLinePrice > 0) {
            $orderProduct = new OrderProduct();
            $orderProduct->quantity = 1;
            $orderProduct->product_id = null;
            $orderProduct->name = $extraOrderLineName ?: 'Extra';
            $orderProduct->price = 0 - $extraOrderLinePrice;
            $orderProduct->discount = 0;
            $orderProduct->order_id = $newOrder->id;
            $orderProduct->sku = Str::slug($orderProduct->name);
            $orderProduct->vat_rate = 21;
            $orderProduct->save();

            $price = $orderProduct->price;

            $newOrder->total += $price;

            if ($calculateInclusiveTax) {
                $taxPrice = $price / 121 * 21;
            } else {
                $taxPrice = $price / 100 * 21;
            }

            $newOrder->btw += $taxPrice;

            $vatRate = 21; //Hardcoded BTW percentage
            if (!isset($vatPercentagesForOrder[$vatRate])) {
                $vatPercentagesForOrder[$vatRate] = 0;
            }
            $vatPercentagesForOrder[$vatRate] += number_format($taxPrice, 2);
            if (!isset($vatPercentagesCount[$vatRate])) {
                $vatPercentagesCount[$vatRate] = 0;
            }
            $vatPercentagesCount[$vatRate] += 1;
        }

        $newOrder->subtotal = $newOrder->total;

        if ($refundDiscountCosts && $discountToGet > 0.00) {
            if ($calculateInclusiveTax) {
                $newOrder->btw += ($discountToGet / (100 + ($vatPercentages / $vatPercentageCount)) * ($vatPercentages / $vatPercentageCount));
            } else {
                $newOrder->btw += ($discountToGet / 100 * ($vatPercentages / $vatPercentageCount));
            }

            $newOrder->total += $discountToGet;
            $discountToSave += $discountToGet;

            $totalVatBeforeDiscount = array_sum($vatPercentagesForOrder);

            foreach ($vatPercentagesForOrder as $vatRate => $vatPrice) {
                $vatPercentagesForOrder[$vatRate] = $newOrder->btw / 100 * (100 * ($vatPercentagesCount[$vatRate] / array_sum($vatPercentagesCount)));
            }
        }

        $newOrder->discount = 0 - $discountToSave;
        $newOrder->vat_percentages = $vatPercentagesForOrder;

        $newOrder->save();
        $newOrder->refresh();
        $this->refillGiftcardFromPaidOrder();

        if ($paymentMethodId) {
            $newOrderPayment = $newOrder->orderPayments()->create([
                'payment_method_id' => $paymentMethodId,
                'payment_method' => PaymentMethod::find($paymentMethodId)->name,
                'amount' => $newOrder->total,
                'psp' => 'own',
            ]);
            $newOrderPayment->psp = 'own';
            $newOrderPayment->save();
            $newOrderPayment->changeStatus('paid');
        }

        $newOrder->createInvoice();

        if ($sendCustomerEmail) {
            try {
                Mail::to($this->email)->send(new OrderCancelledWithCreditMail($newOrder));
                //                $createCreditInvoice ? Mail::to($this->email)->send(new OrderCancelledWithCreditMail($newOrder)) : Mail::to($this->email)->send(new OrderCancelledMail($newOrder));
                //                if ($createCreditInvoice) {
                //                    Mail::to($this->email)->send(new OrderCancelledWithCreditMail($newOrder));
                //                } else {
                //                    Mail::to($this->email)->send(new OrderCancelledMail($newOrder));
                //                }
                $tag = app()->runningInConsole() ? 'order.system.cancelled.mail.send' : 'order.cancelled.mail.send';
            } catch (\Exception $e) {
                $tag = app()->runningInConsole() ? 'order.system.cancelled.mail.send.failed' : 'order.cancelled.mail.send.failed';
                $error = 'Error: ' . $e->getMessage();
            }
            OrderLog::createLog(orderId: $newOrder->id, tag: $tag, note: $error ?? null);
            $newOrder->invoice_send_to_customer = 1;
            $newOrder->save();
        }

        //Always send the invoice to admins
        try {
            //                foreach (Mails::getAdminNotificationEmails() as $notificationInvoiceEmail) {
            Mail::to(Mails::getAdminNotificationEmails())->send(new AdminOrderCancelledMail($newOrder));
            //                }
        } catch (\Exception $e) {
        }

        if ($restock) {
            $newOrder->refillStock(false);
        }

        $this->changeFulfillmentStatus($fulfillmentStatus);

        $this->updateOrderProductsProductInformation();

        return $newOrder;
    }

    public function sendGAEcommerceHit()
    {
        if ($this->ga_user_id && !$this->ga_commerce_hit_send && app()->isProduction() && Customsetting::get('google_analytics_id')) {
            if (!Customsetting::get('google_tagmanager_id')) {
                $data = [
                    'v' => 1,
                    'tid' => Customsetting::get('google_analytics_id'),
                    'cid' => $this->ga_user_id,
                    't' => 'event',
                ];

                $data['ti'] = $this->invoice_id;
                $data['ta'] = url('/');
                $data['tr'] = $this->total;
                $data['tt'] = $this->btw;
                $data['cu'] = 'EUR';
                $data['pa'] = 'purchase';
                $url = 'https://www.google-analytics.com/collect';
                $content = http_build_query($data);
                $content = utf8_encode($content);
                $user_agent = urlencode($_SERVER['HTTP_USER_AGENT']);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-type: application/x-www-form-urlencoded']);
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
                curl_exec($ch);
                curl_close($ch);
            }

            $this->ga_commerce_hit_send = 1;
            $this->save();
        }
    }

    public function downloadInvoiceUrl(): ?string
    {
        if (Storage::disk('dashed')->exists($this->invoicePath())) {
            return route('dashed.frontend.download-invoice', ['orderHash' => $this->hash]);
        }

        $this->createInvoice();

        if (Storage::disk('dashed')->exists($this->invoicePath())) {
            return route('dashed.frontend.download-invoice', ['orderHash' => $this->hash]);
        }

        return null;
    }

    public function downloadPackingslipUrl(): ?string
    {
        if (Storage::disk('dashed')->exists('dashed/packing-slips/packing-slip-' . ($this->invoice_id ?: $this->id) . '-' . $this->hash . '.pdf')) {
            return route('dashed.frontend.download-packing-slip', ['orderHash' => $this->hash]);
        }

        $this->createPackingSlip();

        if (Storage::disk('dashed')->exists('dashed/packing-slips/packing-slip-' . ($this->invoice_id ?: $this->id) . '-' . $this->hash . '.pdf')) {
            return route('dashed.frontend.download-packing-slip', ['orderHash' => $this->hash]);
        }

        return null;
    }

    public function invoicePath(): ?string
    {
        return '/dashed/invoices/invoice-' . ($this->invoice_id ?: $this->id) . '-' . $this->hash . '.pdf';
    }

    public function packingSlipPath(): ?string
    {
        return 'dashed/packing-slips/packing-slip-' . ($this->invoice_id ?: $this->id) . '-' . $this->hash . '.pdf';
    }

    public function getUrl()
    {
        $completeUrl = ShoppingCart::getCompleteUrl();

        return $completeUrl . '?orderId=' . $this->hash . '&paymentId=' . ($this->orderPayments()->count() ? $this->orderPayments()->latest()->first()->hash : '');
    }

    public function fulfillmentStatus()
    {
        if (!$this->credit_for_order_id) {
            if ($this->fulfillment_status == 'unhandled') {
                return [
                    'status' => Orders::getFulfillmentStatusses()[$this->fulfillment_status] ?? '',
                    'color' => 'danger',
                ];
            } else {
                return [
                    'status' => Orders::getFulfillmentStatusses()[$this->fulfillment_status] ?? '',
                    'color' => 'success',
                ];
            }
        } else {
            if ($this->retour_status == 'unhandled') {
                return [
                    'status' => $this->retourStatus(),
                    'color' => 'danger',
                ];
            } else {
                return [
                    'status' => $this->retourStatus(),
                    'color' => 'success',
                ];
            }
        }
        //        return Orders::getFulfillmentStatusses()[$this->fulfillment_status]['name'] ?? '';
    }

    public function orderStatus()
    {
        if ($this->status == 'pending') {
            return [
                'status' => 'Lopende aankoop',
                'color' => 'primary',
            ];
        } elseif ($this->status == 'cancelled') {
            return [
                'status' => 'Geannuleerd',
                'color' => 'danger',
            ];
        } elseif ($this->status == 'waiting_for_confirmation') {
            return [
                'status' => 'Wachten op bevestiging betaling',
                'color' => 'purple',
            ];
        } elseif ($this->status == 'return') {
            return [
                'status' => 'Retour',
                'color' => 'warning',
            ];
        } elseif ($this->status == 'partially_paid') {
            return [
                'status' => 'Gedeeltelijk betaald',
                'color' => 'warning',
            ];
        } else {
            return [
                'status' => 'Betaald',
                'color' => 'success',
            ];
        }
    }

    public function retourStatus()
    {
        if ($this->retour_status == 'unhandled') {
            return 'Niet afgehandeld';
        } elseif ($this->retour_status == 'handled') {
            return 'Afgehandeld';
        } elseif ($this->retour_status == 'received') {
            return 'Ontvangen';
        } elseif ($this->retour_status == 'shipped') {
            return 'Verzonden';
        } elseif ($this->retour_status == 'waiting_for_return') {
            return 'Wachten op retour';
        }
    }

    public function eligibleForFulfillmentProvider(string $provider): bool
    {
        return (bool)$this->orderProducts()->whereHas('product', function ($query) use ($provider) {
            $query->where('fulfillment_provider', $provider);
        })->count();
    }

    public function printReceipt($isCopy = false)
    {
        $store_name = Customsetting::get('site_name');
        $store_address = Customsetting::get('company_street') . ' ' . Customsetting::get('company_street_number') . ', ' . Customsetting::get('company_postal_code') . ' ' . Customsetting::get('company_city');
        $store_phone = Customsetting::get('company_phone_number');
        $store_email = Customsetting::get('site_to_email');
        $store_website = url('/');

        // Init printer
        $printer = new ReceiptPrinter();
        $printer->init(
            Customsetting::get('receipt_printer_connector_type'),
            Customsetting::get('receipt_printer_connector_descriptor')
        );

        $printer->setStore($store_name, $store_address, $store_phone, $store_email, $store_website);
        $printer->setOrder($this);
        $printer->setQRcode('order-' . $this->id);
        $printer->setLogo(public_path('/receipts/logo/logo.png'));
        $printer->printReceipt($isCopy);
    }

    public function getDiscountTaxAttribute()
    {
        $discountTax = 0.00;

        if ($this->discount > 0.00) {
            $totalPriceBeforeDiscount = $this->total + $this->discount;
            $percentageDiscountOfTotalPrice = $this->discount / $totalPriceBeforeDiscount * 100;
            foreach ($this->vat_percentages as $vatPercentage => $amount) {
                $discountTax += ($amount * (100 / $percentageDiscountOfTotalPrice)) / 100 * $percentageDiscountOfTotalPrice;
            }
        }

        return $discountTax;
    }

    public function getDiscountWithoutTaxAttribute()
    {
        return $this->discount - $this->discountTax;
    }

    public function addTrackAndTrace(?string $supplier = null, ?string $deliveryCompany = null, ?string $code = null, ?string $url = null, ?string $expectedDeliveryDate = null): void
    {
        if (!$this->trackAndTraces()->where('supplier', $supplier)->where('delivery_company', $deliveryCompany)->where('code', $code)->exists()) {
            $this->trackAndTraces()->create([
                'supplier' => $supplier,
                'delivery_company' => $deliveryCompany,
                'code' => $code,
                'url' => $url,
                'expected_delivery_date' => $expectedDeliveryDate,
            ]);
        }
    }

    public function customOrderFields(bool $onlyOnInvoice = false): array
    {
        $customOrderFields = [];

        foreach (ecommerce()->builder('customOrderFields') as $key => $field) {
            $key = str($key)->snake()->toString();

            if ($this->$key && (!$onlyOnInvoice || ($onlyOnInvoice && ($field['showOnInvoice'] ?? false)))) {
                $customOrderFields[$field['label']] = $this->$key;
            }
        }

        return $customOrderFields;
    }

    public function fulfillmentCompanies(): array
    {
        $fulfillmentCompanies = [];

        foreach ($this->orderProducts as $orderProduct) {
            if ($orderProduct && $orderProduct->fulfillment_provider) {
                if ($orderProduct->product->fulfillmentCompany) {
                    $fulfillmentCompanies[$orderProduct->fulfillmentCompany->id] = $orderProduct->fulfillmentCompany->name;
                } else {
                    $fulfillmentCompanies[$orderProduct->fulfillment_provider] = $orderProduct->fulfillment_provider;
                }
            }
        }

        return $fulfillmentCompanies;
    }

    public function sendAutomaticFulfillmentProducts(): void
    {
        foreach (FulfillmentCompany::where('process_automatically', true)->get() as $fulfillmentCompany) {
            $orderProducts = $this->orderProducts()->where('fulfillment_provider', $fulfillmentCompany->id)->get()->toArray();
            $fulfillmentCompany->sendOrder($this, $orderProducts);
        }
    }

    public function printInvoice(): void
    {
        $printerName = Customsetting::get('invoice_printer_connector_descriptor');

        if ($printerName) {
            $this->createInvoice();

            $pdfPath = 'files-to-download/' . time() . rand(10000, 100000) . '.pdf';

            $content = Storage::disk('dashed')->get($this->invoicePath());
            Storage::disk('public')->put($pdfPath, $content);

            $pdfPath = Storage::disk('public')->path($pdfPath);

            Printing::print($printerName, $pdfPath);

            $pdfPath = Storage::disk('public')->delete($pdfPath);
        }
    }

    public function printPackingSlip(): void
    {
        $printerName = Customsetting::get('packing_slip_printer_connector_descriptor');
        if ($printerName) {
            $this->createPackingSlip();

            $pdfPath = 'files-to-download/' . time() . rand(10000, 100000) . '.pdf';

            $content = Storage::disk('dashed')->get($this->packingSlipPath());
            Storage::disk('public')->put($pdfPath, $content);

            $pdfPath = Storage::disk('public')->path($pdfPath);
            Printing::print($printerName, $pdfPath);

            $pdfPath = Storage::disk('public')->delete($pdfPath);
        }
    }

    public function getCountryCodeAttribute(): ?string
    {
        return Countries::getCountryIsoCode($this->country) ?: 'NL';
    }
}
