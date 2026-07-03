{{-- POS Alpine-logica POSData() — gedeeld tussen klassieke en moderne layout. Verplaatst uit point-of-sale.blade.php (regel 1917-3929). --}}
@script
<script>
    Alpine.data('POSData', () => ({
        cartInstance: 'point-of-sale',
        orderOrigin: 'pos',
        posIdentifier: '',
        userId: {{ auth()->user()->id }},
        searchQueryInputmode: $wire.entangle('searchQueryInputmode'),
        searchProductQuery: '',
        searchStockProductQuery: '',
        lastOrder: null,
        orders: [],
        orderAmountToSkip: 0,
        selectedOrder: null,
        selectedStockProduct: null,
        searchOrderQuery: '',
        products: [],
        allProducts: [],
        searchedStockProducts: [],
        loadingSearchedStockProducts: false,
        loadingSearchedProducts: false,
        discountCode: null,
        discount: null,
        vat: null,
        vatPercentages: [],
        isExVat: false,
        subTotal: null,
        subTotalIncl: null,
        subTotalEx: null,
        total: null,
        totalUnformatted: null,
        activeDiscountCode: null,
        appliedDiscountCodes: [],
        giftCards: [],
        giftCardsTotal: null,
        giftCardsTotalUnformatted: 0,
        searchedProducts: [],
        paymentMethods: [],
        order: null,
        suggestedCashPaymentAmounts: [],
        submittingPayment: false,
        chosenPaymentMethod: null,
        isPinTerminalPayment: false,
        pinTerminalStatus: false,
        pinTerminalError: false,
        pinTerminalErrorMessage: false,
        cashPaymentAmount: null,
        orderPayments: [],
        firstPaymentMethod: null,
        pinTerminalIntervalId: null,
        shippingMethods: [],
        shippingMethod: null,
        shippingMethodId: null,
        shippingMethodCosts: null,
        shippingMethodCostsUnformatted: null,
        postPay: null,
        orderUrl: null,
        productToChange: $wire.entangle('productToChange'),
        totalQuantity() {
            return this.products.reduce((sum, product) => sum + product.quantity, 0);
        },

        customProductPopup: false,
        createDiscountPopup: false,
        redeemGiftCardPopup: false,
        customerDataPopup: false,
        checkoutPopup: false,
        paymentPopup: false,
        ordersPopup: false,
        stockPopup: false,
        cancelOrderPopup: false,
        orderConfirmationPopup: false,
        chooseShippingMethodPopup: false,
        changeProductPricePopup: false,
        isFullscreen: false,
        pinTerminalStatusHandled: false,
        loading: false,

        customerUserId: $wire.entangle('customerUserId'),
        firstName: $wire.entangle('firstName'),
        lastName: $wire.entangle('lastName'),
        phoneNumber: $wire.entangle('phoneNumber'),
        email: $wire.entangle('email'),
        street: $wire.entangle('street'),
        houseNr: $wire.entangle('houseNr'),
        zipCode: $wire.entangle('zipCode'),
        city: $wire.entangle('city'),
        country: $wire.entangle('country'),
        company: $wire.entangle('company'),
        btwId: $wire.entangle('btwId'),
        invoiceStreet: $wire.entangle('invoiceStreet'),
        invoiceHouseNr: $wire.entangle('invoiceHouseNr'),
        invoiceZipCode: $wire.entangle('invoiceZipCode'),
        invoiceCity: $wire.entangle('invoiceCity'),
        invoiceCountry: $wire.entangle('invoiceCountry'),
        note: $wire.entangle('note'),
        customFields: $wire.entangle('customFields'),

        hasCashRegister: {{ Customsetting::get('cash_register_available', null, false) ? 'true' : 'false' }},
        productQueue: [],
        isProcessingProductQueue: false,
        productQueueDelay: 500,


        time: '',

        updateTime() {
            const now = new Date()
            const h = String(now.getHours()).padStart(2, '0')
            const m = String(now.getMinutes()).padStart(2, '0')
            const s = String(now.getSeconds()).padStart(2, '0')
            this.time = `${h}:${m}:${s}`
        },

        toggle(variable) {
            this.loading = true;
            if (variable in this) {
                if (this[variable]) {
                    this.focus();
                }
                this[variable] = !this[variable];
            }
            this.loading = false;
        },

        disable(variable) {
            if (variable in this) {
                this[variable] = false;
            }
            this.focus();
        },

        enable(variable) {
            if (variable in this) {
                this[variable] = true;
            }
        },

        async openCashRegister() {
            this.loading = true;

            try {
                let response = await fetch('{{ route('api.point-of-sale.open-cash-register') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    }
                });


                let data = await response.json();

                if (!response.ok) {
                    this.loading = false;
                    this.focus();
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                $wire.dispatch('notify', {
                    type: 'success',
                    message: 'De kassa is geopend'
                })


                this.focus();
                this.loading = false;
            } catch (error) {

                this.loading = false;
                this.focus();
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De kassa kon niet worden geopend'
                })
            }
        },

        async initialize() {
            try {
                let response = await fetch('{{ route('api.point-of-sale.initialize') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    },
                    body: JSON.stringify({
                        userId: this.userId
                    })
                });

                let data = await response.json();

                if (!response.ok) {
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                this.posIdentifier = data.posIdentifier;
                this.products = data.products;
                this.lastOrder = data.lastOrder;
                this.shippingMethods = data.shippingMethods;
                this.shippingMethodId = data.shippingMethodId;
                this.shippingMethodCosts = data.shippingMethodCosts;
                this.customerUserId = data.customerUserId;
                this.firstName = data.firstName;
                this.lastName = data.lastName;
                this.phoneNumber = data.phoneNumber;
                this.email = data.email;
                this.street = data.street;
                this.houseNr = data.houseNr;
                this.zipCode = data.zipCode;
                this.city = data.city;
                this.company = data.company;
                this.country = data.country;
                this.btwId = data.btwId;
                this.invoiceStreet = data.invoiceStreet;
                this.invoiceHouseNr = data.invoiceHouseNr;
                this.invoiceZipCode = data.invoiceZipCode;
                this.invoiceCity = data.invoiceCity;
                this.invoiceCountry = data.invoiceCountry;
                this.note = data.note;
                this.discountCode = data.discountCode;
                this.customFields = data.customFields;
                this.retrieveCart();
                this.focus();
            } catch (error) {
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De winkelwagen kon niet worden gestart'
                })
            }
        },

        async getAllProducts(clearCache = false) {
            try {
                let response = await fetch('{{ route('api.point-of-sale.get-all-products') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    },
                    body: JSON.stringify({
                        userId: this.userId,
                        clearCache: clearCache,
                    })
                });

                let data = await response.json();

                if (!response.ok) {
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                this.allProducts = (data.products || []).map(p => ({
                    ...p,
                    _searchHaystack: (p.search || '').toLowerCase(),
                }));
            } catch (error) {
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De producten kon niet worden opgehaald'
                })
            }
        },

        async retrieveCart(applyDiscountCode = null) {
            // retrieveCart is een pure refresh: het past ALLEEN een kortingscode toe
            // als er expliciet eentje wordt meegegeven (scan-as-code flow). Zonder
            // argument wordt er niets toegepast, zodat een net verwijderde code niet
            // per ongeluk opnieuw wordt toegevoegd bij het verversen van de mand.
            this.loading = true;
            try {
                let response = await fetch('{{ route('api.point-of-sale.retrieve-cart') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    },
                    body: JSON.stringify({
                        cartInstance: this.cartInstance,
                        posIdentifier: this.posIdentifier,
                        discountCode: applyDiscountCode,
                    })
                });

                let data = await response.json();

                if (!response.ok) {
                    $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                } else {
                    this.products = data.products;
                    this.discountCode = data.discountCode;
                    this.activeDiscountCode = data.activeDiscountCode;
                    this.discount = data.discount;
                    this.vat = data.vat;
                    this.vatPercentages = data.vatPercentages;
                    this.isExVat = data.isExVat ?? false;
                    this.subTotal = data.subTotal;
                    this.subTotalIncl = data.subTotalIncl ?? data.subTotal;
                    this.subTotalEx = data.subTotalEx ?? data.subTotal;
                    this.total = data.total;
                    this.totalUnformatted = data.totalUnformatted;
                    this.shippingMethods = data.shippingMethods;
                    this.shippingMethodId = data.shippingMethodId;
                    this.shippingMethodCosts = data.shippingCosts;
                    this.shippingMethodCostsUnformatted = data.shippingCostsUnformatted;
                    this.appliedDiscountCodes = data.discountCodes || [];
                    this.giftCards = data.giftCards || [];
                    this.giftCardsTotal = data.giftCardsTotal;
                    this.giftCardsTotalUnformatted = data.giftCardsTotalUnformatted ?? 0;
                    this.paymentMethods = data.paymentMethods;
                }

            } catch (error) {
                $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De winkelwagen kon niet worden opgehaald'
                })
            }

            this.loading = false;
            return true;
        },

        async printLastOrder() {
            try {
                let response = await fetch('{{ route('api.point-of-sale.print-receipt') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    },
                    body: JSON.stringify({
                        orderId: this.lastOrder.id,
                        isCopy: true,
                    })
                });

                let data = await response.json();

                this.focus();

                if (!response.ok) {
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }
            } catch (error) {
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De bon kon niet worden geprint'
                })
            }
        },

        async printReceipt() {
            this.loading = true;

            if (!this.order) {
                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'Er is geen bestelling om te printen',
                })
            }

            try {
                let response = await fetch('{{ route('api.point-of-sale.print-receipt') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    },
                    body: JSON.stringify({
                        orderId: this.order.id,
                        isCopy: false,
                    })
                });

                let data = await response.json();

                this.focus();

                if (!response.ok) {
                    this.loading = false;
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'success',
                    message: 'Bon geprint'
                })
            } catch (error) {
                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De bon kon niet worden geprint'
                })
            }
        },

        async printOrder(order) {
            this.loading = true;
            try {
                let response = await fetch('{{ route('api.point-of-sale.print-receipt') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    },
                    body: JSON.stringify({
                        orderId: order.id,
                        isCopy: true,
                    })
                });

                let data = await response.json();

                this.focus();

                if (!response.ok) {
                    this.loading = false;
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'success',
                    message: 'Bon geprint'
                })
            } catch (error) {
                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De bon kon niet worden geprint'
                })
            }
        },

        async sendInvoice(order) {
            this.loading = true;
            try {
                let response = await fetch('{{ route('api.point-of-sale.send-invoice') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    },
                    body: JSON.stringify({
                        orderId: order.id,
                        isCopy: true,
                    })
                });

                let data = await response.json();

                this.focus();

                if (!response.ok) {
                    this.loading = false;
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'success',
                    message: 'Factuur verstuurd'
                })
            } catch (error) {
                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De factuur kon niet worden verstuurd'
                })
            }
        },

        async sendPaymentLink(order) {
            this.loading = true;
            try {
                let response = await fetch('{{ route('api.point-of-sale.send-payment-link') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    },
                    body: JSON.stringify({
                        orderId: order.id,
                    })
                });

                let data = await response.json();

                this.focus();

                if (!response.ok) {
                    this.loading = false;
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'success',
                    message: 'Betaallink verstuurd'
                })
            } catch (error) {
                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De betaallink kon niet worden verstuurd'
                })
            }
        },

        async updateSearchedProducts() {
            this.loading = true;
            try {
                let response = await fetch('{{ route('api.point-of-sale.search-products') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    },
                    body: JSON.stringify({
                        search: this.searchProductQuery,
                    })
                });

                let data = await response.json();

                this.searchedProducts = data.products;

                if (!response.ok) {
                    this.loading = false;
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }
                this.loading = false;
            } catch (error) {
                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De gezochte producten konden niet worden opgehaald'
                })
            }
        },

        async addProduct(productId) {
            this.loading = true;
            try {
                let response = await fetch('{{ route('api.point-of-sale.add-product') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    },
                    body: JSON.stringify({
                        productSearchQuery: this.searchProductQuery,
                        productId: productId,
                        posIdentifier: this.posIdentifier,
                    })
                });

                let data = await response.json();
                this.focus();

                this.searchedProducts = [];
                this.searchProductQuery = '';
                this.loadingSearchedProducts = false;

                if (!response.ok) {
                    this.loading = false;
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                this.products = data.products;
                this.retrieveCart();
                this.loading = false;
            } catch (error) {
                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De gezochte producten konden niet worden opgehaald'
                })
            }
        },

        async addCustomProduct() {
            await $wire.$commit();
            const formData = await $wire.get('customProductData');
            const name = (formData?.name ?? '').trim();
            const price = parseFloat(formData?.price ?? 0);
            const quantity = parseInt(formData?.quantity ?? 1);
            const vatRate = parseFloat(formData?.vat_rate ?? 21);

            if (!name) {
                return $wire.dispatch('notify', {type: 'danger', message: 'Productnaam is verplicht'});
            }

            this.loading = true;
            try {
                let response = await fetch('{{ route('api.point-of-sale.add-custom-product') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    },
                    body: JSON.stringify({
                        posIdentifier: this.posIdentifier,
                        name: name,
                        price: price,
                        quantity: quantity,
                        vat_rate: vatRate,
                    })
                });

                let data = await response.json();

                if (!response.ok) {
                    this.loading = false;
                    return $wire.dispatch('notify', {type: 'danger', message: data.message});
                }

                this.products = data.products;
                this.customProductPopup = false;
                $wire.set('customProductData', {name: '', quantity: 1, vat_rate: 21, price: 0});
                $wire.dispatch('resetNumpad');
                this.retrieveCart();
                this.focus();
                this.loading = false;

                $wire.dispatch('notify', {type: 'success', message: 'Aangepast product toegevoegd'});
            } catch (error) {
                this.loading = false;
                return $wire.dispatch('notify', {type: 'danger', message: 'Er ging iets fout bij het toevoegen'});
            }
        },

        selectProduct() {
            const query = this.searchProductQuery?.trim();

            if (!query) {
                return;
            }

            this.productQueue.push({
                productSearchQuery: query,
                posIdentifier: this.posIdentifier,
            });

            this.searchProductQuery = '';
            this.focus();

            this.processProductQueue();
        },

        async processProductQueue() {
            if (this.isProcessingProductQueue) {
                return;
            }

            this.isProcessingProductQueue = true;

            while (this.productQueue.length > 0) {
                const item = this.productQueue.shift();

                this.loading = true;

                try {
                    const response = await fetch('{{ route('api.point-of-sale.select-product') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                        },
                        body: JSON.stringify({
                            productSearchQuery: item.productSearchQuery,
                            posIdentifier: item.posIdentifier,
                        })
                    });

                    const contentType = response.headers.get('content-type') || '';
                    let data = {};

                    if (contentType.includes('application/json')) {
                        data = await response.json();
                    } else {
                        const text = await response.text();
                        console.error('Non-JSON response:', text);
                        throw new Error('Server returned no JSON response');
                    }

                    if (!response.ok) {
                        $wire.dispatch('notify', {
                            type: 'danger',
                            message: data.message || `Er ging iets mis bij het ophalen van product: ${item.productSearchQuery}`,
                        });
                    } else {
                        this.products = data.products;

                        let codeToApply = null;
                        if (data.discountCode) {
                            codeToApply = data.discountCode;
                        } else if (data.order) {
                            this.showOrdersPopup();
                            this.selectedOrder = data.order;
                        }

                        this.retrieveCart(codeToApply);
                        this.searchedProducts = [];
                        this.focus();
                    }
                } catch (error) {
                    console.error(error);

                    $wire.dispatch('notify', {
                        type: 'danger',
                        message: `Het product "${item.productSearchQuery}" kon niet worden opgehaald`,
                    });
                }

                await new Promise(resolve => setTimeout(resolve, this.productQueueDelay));
            }

            this.loading = false;
            this.isProcessingProductQueue = false;
        },

        async changeQuantity(productIdentifier, quantity) {
            this.loading = true;
            try {
                let response = await fetch('{{ route('api.point-of-sale.change-quantity') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    },
                    body: JSON.stringify({
                        posIdentifier: this.posIdentifier,
                        productIdentifier: productIdentifier,
                        quantity: quantity,
                    })
                });

                let data = await response.json();
                this.focus();

                if (!response.ok) {
                    this.loading = false;
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                if (this.products.length && data.products.length === 0) {
                    this.removeDiscount();
                }

                this.products = data.products;
                this.retrieveCart();
                this.loading = false;
            } catch (error) {
                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De gezochte producten konden niet worden opgehaald'
                })
            }
        },

        async clearProducts() {
            this.loading = true;
            try {
                let response = await fetch('{{ route('api.point-of-sale.clear-products') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    },
                    body: JSON.stringify({
                        posIdentifier: this.posIdentifier,
                    })
                });

                let data = await response.json();
                this.focus();

                if (!response.ok) {
                    this.loading = false;
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                this.products = data.products;
                this.retrieveCart();
                this.loading = false;
            } catch (error) {
                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De winkelmand kon niet worden geleegd'
                })
            }
        },

        async removeDiscount() {
            this.loading = true;
            try {
                let response = await fetch('{{ route('api.point-of-sale.remove-discount') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    },
                    body: JSON.stringify({
                        posIdentifier: this.posIdentifier,
                    })
                });

                let data = await response.json();
                this.focus();

                if (!response.ok) {
                    this.loading = false;
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                this.discountCode = null;
                this.activeDiscountCode = null;
                this.retrieveCart();
                this.loading = false;

            } catch (error) {
                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De korting kon niet worden verwijderd'
                })
            }
        },

        async openChangeProductPricePopup(product) {
            this.loading = true;
            await $wire.openChangeProductForm(product);
            this.toggle('changeProductPricePopup');
            this.loading = false;
        },

        async selectShippingMethod(shippingMethodId) {
            this.loading = true;
            try {
                let response = await fetch('{{ route('api.point-of-sale.select-shipping-method') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    },
                    body: JSON.stringify({
                        posIdentifier: this.posIdentifier,
                        cartInstance: this.cartInstance,
                        orderOrigin: this.orderOrigin,
                        shippingMethodId: shippingMethodId,
                        userId: this.userId,
                    })
                });

                let data = await response.json();

                if (!response.ok) {
                    this.loading = false;
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                this.shippingMethodId = data.shippingMethodId;
                this.shippingMethodCosts = data.shippingMethodCosts;

                this.toggle('chooseShippingMethodPopup');
                await this.retrieveCart();
                this.focus();
                this.loading = false;

            } catch (error) {
                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De verzendmethode kon niet worden geselecteerd'
                })
            }
        },

        async removeShippingMethod() {
            this.loading = true;
            try {
                let response = await fetch('{{ route('api.point-of-sale.remove-shipping-method') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    },
                    body: JSON.stringify({
                        posIdentifier: this.posIdentifier,
                        cartInstance: this.cartInstance,
                        orderOrigin: this.orderOrigin,
                        userId: this.userId,
                    })
                });

                let data = await response.json();
                this.focus();

                if (!response.ok) {
                    this.loading = false;
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                this.shippingMethodId = null;
                this.retrieveCart();
                this.focus();
                this.loading = false;

            } catch (error) {
                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De verzendmethode kon niet worden verwijderd'
                })
            }
        },

        async selectPaymentMethod(paymentMethodId) {
            this.loading = true;
            try {
                let response = await fetch('{{ route('api.point-of-sale.select-payment-method') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    },
                    body: JSON.stringify({
                        order: this.selectedOrder,
                        posIdentifier: this.posIdentifier,
                        cartInstance: this.cartInstance,
                        orderOrigin: this.orderOrigin,
                        paymentMethodId: paymentMethodId,
                        userId: this.userId,
                    })
                });

                let data = await response.json();
                this.focus();

                if (!response.ok) {
                    this.loading = false;
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                this.isPinTerminalPayment = data.isPinTerminalPayment;
                this.chosenPaymentMethod = data.paymentMethod;
                this.suggestedCashPaymentAmounts = data.suggestedCashPaymentAmounts;
                this.order = data.order;
                this.postPay = data.postPay;
                this.orderUrl = data.orderUrl;

                // Bestelling al volledig betaald via cadeaubon(nen) — backend
                // heeft 'm op 'paid' gezet. Skip betaalmethode + pinpopup en
                // toon direct de bevestigingspopup.
                if (data.alreadyPaid) {
                    this.products = [];
                    this.discountCode = '';
                    this.cashPaymentAmount = null;
                    this.orderPayments = [];
                    this.firstPaymentMethod = { name: 'Cadeaubon', is_cash_payment: false };
                    this.disable('checkoutPopup');
                    this.enable('orderConfirmationPopup');
                    $wire.$refresh();
                    this.loading = false;
                    return;
                }

                this.disable('checkoutPopup');
                this.enable('paymentPopup');

                if (this.isPinTerminalPayment) {
                    this.startPinTerminalPayment();
                }
                this.loading = false;

            } catch (error) {
                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De betaalmethode kon niet worden geselecteerd'
                })
            }
        },

        async saveCustomerData() {
            this.loading = true;
            try {
                let response = await fetch('{{ route('api.point-of-sale.update-customer-data') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    },
                    body: JSON.stringify({
                        posIdentifier: this.posIdentifier,
                        cartInstance: this.cartInstance,
                        orderOrigin: this.orderOrigin,
                        userId: this.userId,
                    })
                });

                let data = await response.json();
                this.focus();

                if (!response.ok) {
                    this.loading = false;
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                this.toggle('customerDataPopup');
                this.retrieveCart();
                this.loading = false;

            } catch (error) {
                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De klant gegevens kon niet worden opgeslagen'
                })
            }
        },

        async startPinTerminalPayment(hasMultiplePayments = false) {
            this.isPinTerminalPayment = true;
            this.pinTerminalStatusHandled = false;
            try {
                let response = await fetch('{{ route('api.point-of-sale.start-pin-terminal-payment') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    },
                    body: JSON.stringify({
                        posIdentifier: this.posIdentifier,
                        order: this.order,
                        paymentMethod: this.chosenPaymentMethod,
                        userId: this.userId,
                        hasMultiplePayments: hasMultiplePayments,
                    })
                });

                let data = await response.json();
                this.focus();

                if (!response.ok) {
                    this.pinTerminalStatus = data.pinTerminalStatus;
                    this.pinTerminalError = data.pinTerminalError;
                    this.pinTerminalErrorMessage = data.pinTerminalErrorMessage;

                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                this.pinTerminalStatus = data.pinTerminalStatus;
                this.pinTerminalError = data.pinTerminalError;
                this.pinTerminalErrorMessage = data.pinTerminalErrorMessage;

                if (this.pinTerminalStatus == 'pending') {
                    this.checkPinTerminalPayment();
                }

            } catch (error) {
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De pin betaling kon niet worden gestart'
                })
            }
        },

        async createPaymentWithExtraPayment() {
            this.markAsPaid(true);
        },

        async closePayment() {
            this.loading = true;
            if (this.selectedOrder) {
                this.clearProducts();
            }
            this.selectedOrder = '';
            try {
                let response = await fetch('{{ route('api.point-of-sale.close-payment') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    },
                    body: JSON.stringify({
                        posIdentifier: this.posIdentifier,
                        order: this.order,
                    })
                });

                let data = await response.json();
                this.focus();

                if (!response.ok) {
                    this.loading = false;
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    });
                }

                $wire.dispatch('notify', {
                    type: 'success',
                    message: data.message,
                });

                this.isPinTerminalPayment = false;
                this.order = null;
                this.toggle('paymentPopup');
                this.loading = false;

            } catch (error) {
                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De betaling kon niet worden gesloten'
                })
            }
        },

        async setCashPaymentAmount(amount) {
            this.cashPaymentAmount = amount;
            await this.markAsPaid();
        },

        async markAsPaid(hasMultiplePayments = false) {
            if (this.submittingPayment) {
                return;
            }
            this.submittingPayment = true;
            this.loading = true;
            try {
                let response = await fetch('{{ route('api.point-of-sale.mark-as-paid') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    },
                    body: JSON.stringify({
                        posIdentifier: this.posIdentifier,
                        order: this.order,
                        paymentMethod: this.chosenPaymentMethod,
                        userId: this.userId,
                        cashPaymentAmount: this.cashPaymentAmount,
                        hasMultiplePayments: hasMultiplePayments,
                    })
                });

                let data = await response.json();
                this.focus();

                if (!response.ok) {
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                if (data.startPinTerminalPayment) {
                    this.startPinTerminalPayment(hasMultiplePayments);
                } else {
                    this.toggle('paymentPopup')
                    this.products = [];
                    this.discountCode = '';
                    this.cashPaymentAmount = null;
                    this.order = data.order;
                    this.orderPayments = data.orderPayments;
                    this.firstPaymentMethod = data.firstPaymentMethod;
                    this.toggle('orderConfirmationPopup')
                    $wire.$refresh();
                }
            } catch (error) {
                $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De bestelling kon niet worden gemarkeerd als betaald'
                })
            } finally {
                this.loading = false;
                this.submittingPayment = false;
            }
        },

        checkPinTerminalPayment() {
            // Ruim een eventueel nog lopend interval op zodat er nooit twee
            // pollers tegelijk draaien (orphan intervals blijven anders pollen).
            this.stopPinTerminalPolling();

            this.pinTerminalIntervalId = setInterval(() => {
                if (this.isPinTerminalPayment && this.pinTerminalStatus == 'pending' && this.order) {
                    console.log('Checking pin terminal payment status...');
                    this.pollPinTerminalPayment();
                } else {
                    console.log('Stopping pin terminal payment status check.');
                    this.stopPinTerminalPolling(); // Stop polling if condition changes
                }
            }, 1000);
        },

        stopPinTerminalPolling() {
            if (this.pinTerminalIntervalId) {
                clearInterval(this.pinTerminalIntervalId);
                this.pinTerminalIntervalId = null;
            }
        },

        async pollPinTerminalPayment() {
            // Zonder order kan de backend niets controleren (400). Kan gebeuren
            // als de kassa gereset wordt terwijl een pinbetaling nog 'pending' is.
            if (!this.order) {
                this.stopPinTerminalPolling();
                return;
            }

            try {
                console.log('Polling pin terminal payment status...');
                let response = await fetch('{{ route('api.point-of-sale.check-pin-terminal-payment') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    },
                    body: JSON.stringify({
                        posIdentifier: this.posIdentifier,
                        order: this.order,
                    })
                });

                let data = await response.json();
                this.focus();

                if (!response.ok) {
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                this.pinTerminalStatus = data.pinTerminalStatus;
                this.pinTerminalError = data.pinTerminalError;
                this.pinTerminalErrorMessage = data.pinTerminalErrorMessage;

                if (this.pinTerminalStatus == 'paid' && !this.pinTerminalStatusHandled) {
                    console.log('Pin terminal payment completed successfully.');
                    this.disable('paymentPopup')
                    this.products = [];
                    this.discountCode = '';
                    this.cashPaymentAmount = null;
                    this.order = data.order;
                    this.orderPayments = data.orderPayments;
                    this.firstPaymentMethod = data.firstPaymentMethod;
                    this.enable('pinTerminalStatusHandled');
                    this.enable('orderConfirmationPopup')
                }

            } catch (error) {
                console.log(error);
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De pin betaling kon niet worden gecontroleerd'
                })
            }
        },

        async updateSearchQueryInputmode() {
            this.searchQueryInputmode = !this.searchQueryInputmode;
            try {
                let response = await fetch('{{ route('api.point-of-sale.update-search-query-input-mode') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    },
                    body: JSON.stringify({
                        searchQueryInputmode: this.searchQueryInputmode,
                        userId: this.userId,
                    })
                });

                let data = await response.json();
                this.focus();

                if (!response.ok) {
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

            } catch (error) {
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De input query status kon niet worden geupdate'
                })
            }
        },

        async resetPOS() {
            this.lastOrder = this.order;
            this.order = null;
            // Breek een eventueel lopende pinbetaling-polling af; anders blijft
            // die pollen met een lege order (400) en klapt de pin-template.
            this.stopPinTerminalPolling();
            this.isPinTerminalPayment = false;
            this.pinTerminalStatus = false;
            this.paymentPopup = false;
            this.orderUrl = null;
            this.postPay = false;
            this.orderConfirmationPopup = false;
            this.customerUserId = null;
            this.firstName = null;
            this.lastName = null;
            this.email = null;
            this.phoneNumber = null;
            this.street = null;
            this.houseNr = null;
            this.zipCode = null;
            this.city = null;
            this.country = null;
            this.company = null;
            this.btwId = null;
            this.invoiceStreet = null;
            this.invoiceHouseNr = null;
            this.invoiceZipCode = null;
            this.invoiceCity = null;
            this.invoiceCountry = null;
            this.note = null;
            this.initialize();
        },

        getSearchedProducts() {
            const query = this.searchProductQuery.trim().toLowerCase();

            if (query.length < 2) {
                this.searchedProducts = [];
                return;
            }

            const words = query.split(/\s+/);

            const filtered = this.allProducts
                .map(product => {
                    const haystack = product._searchHaystack || (product.search || '').toLowerCase();
                    let score = 0;

                    if (haystack === query) {
                        score += 1000;
                    }
                    if (haystack.startsWith(query)) {
                        score += 500;
                    }

                    let allMatch = true;
                    for (const word of words) {
                        if (haystack.includes(word)) {
                            score += 100;
                        } else {
                            allMatch = false;
                        }
                    }
                    if (allMatch) {
                        score += 300;
                    }

                    return score > 0 ? {...product, _score: score} : null;
                })
                .filter(Boolean)
                .sort((a, b) => b._score - a._score)
                .slice(0, 100);

            // Instant render with client-side data
            this.searchedProducts = filtered;
            this.loadingSearchedProducts = false;

            // Enrich stock/prices in background - merge when it arrives
            const requestQuery = query;
            this.enrichSearchedProducts(filtered, requestQuery);
        },

        async enrichSearchedProducts(products, requestQuery) {
            if (!products.length) {
                return;
            }
            try {
                let response = await fetch('{{ route('api.point-of-sale.update-product-info') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    },
                    body: JSON.stringify({
                        products: products,
                        userId: this.userId,
                    })
                });

                if (!response.ok) {
                    return;
                }

                let data = await response.json();

                // Only apply if user hasn't typed further since request
                if (this.searchProductQuery.trim().toLowerCase() !== requestQuery) {
                    return;
                }

                this.searchedProducts = data.products;
                this.focus();
            } catch (error) {
                // Silently ignore - the user already sees the client-side results
            }
        },

        getSearchedStockProducts() {
            const query = this.searchStockProductQuery.trim().toLowerCase();

            if (query.length < 2) {
                this.searchedStockProducts = [];
                this.selectedStockProduct = null;
                return;
            }

            const words = query.split(/\s+/);

            const filtered = this.allProducts
                .map(product => {
                    const haystack = product._searchHaystack || (product.search || '').toLowerCase();
                    let score = 0;

                    if (haystack === query) {
                        score += 1000;
                    }
                    if (haystack.startsWith(query)) {
                        score += 500;
                    }

                    let allMatch = true;
                    for (const word of words) {
                        if (haystack.includes(word)) {
                            score += 100;
                        } else {
                            allMatch = false;
                        }
                    }
                    if (allMatch) {
                        score += 300;
                    }

                    return score > 0 ? {...product, _score: score} : null;
                })
                .filter(Boolean)
                .sort((a, b) => b._score - a._score)
                .slice(0, 100);

            this.searchedStockProducts = filtered;
            this.selectedStockProduct = filtered.length ? filtered[0] : null;
            this.loadingSearchedStockProducts = false;

            this.enrichStockSearchedProducts(filtered, query);
        },

        async enrichStockSearchedProducts(products, requestQuery) {
            if (!products.length) {
                return;
            }
            try {
                let response = await fetch('{{ route('api.point-of-sale.update-product-info') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    },
                    body: JSON.stringify({
                        products: products,
                        userId: this.userId,
                    })
                });

                if (!response.ok) {
                    return;
                }

                let data = await response.json();

                if (this.searchStockProductQuery.trim().toLowerCase() !== requestQuery) {
                    return;
                }

                this.searchedStockProducts = data.products;
                this.selectedStockProduct = data.products?.length ? data.products[0] : null;
            } catch (error) {
                // Silently ignore - client-side results already shown
            }
        },

        async retrieveOrders(append = false) {
            try {
                let response = await fetch('{{ route('api.point-of-sale.retrieve-orders') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    },
                    body: JSON.stringify({
                        userId: this.userId,
                        skip: this.orderAmountToSkip,
                        searchOrderQuery: this.searchOrderQuery,
                    })
                });

                let data = await response.json();

                if (!response.ok) {
                    this.searchOrderQuery = '';
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                if (data.order) {
                    this.orders = [
                        {
                            'date': 'gevonden resultaat',
                            'orders': [
                                data.order
                            ]
                        }
                    ];
                    this.selectedOrder = data.order;
                } else {
                    if (append) {
                        this.orders = this.orders.concat(data.orders);
                    } else {
                        this.orders = data.orders;
                    }
                    if (!this.selectedOrder && data.firstOrder) {
                        this.selectedOrder = data.firstOrder;
                    }
                    this.orderAmountToSkip = this.orderAmountToSkip + data.orders.reduce((acc, group) => acc + group.orders.length, 0);
                }

            } catch (error) {
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'Kan de bestellingen niet ophalen'
                })
            }
        },

        async submitCancelOrderForm() {
            try {
                let response = await fetch('{{ route('api.point-of-sale.cancel-order') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    },
                    body: JSON.stringify({
                        userId: this.userId,
                        order: this.selectedOrder,
                    })
                });

                let data = await response.json();

                if (!response.ok) {
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                this.cancelOrderPopup = false;
                this.openCashRegister();

            } catch (error) {
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'Kan de bestellingen niet ophalen'
                })
            }
        },

        async updateSelectedStockProduct() {
            this.loading = true;
            try {
                let response = await fetch('{{ route('api.point-of-sale.update-product') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    },
                    body: JSON.stringify({
                        userId: this.userId,
                        product: this.selectedStockProduct,
                    })
                });

                let data = await response.json();

                if (!response.ok) {
                    this.loading = false;
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                this.loading = false;
                name = this.selectedStockProduct.name;
                this.searchStockProductQuery = '';
                this.selectedStockProduct = null;
                this.focusSearchProduct();

                return $wire.dispatch('notify', {
                    type: 'success',
                    message: 'De voorraad van ' + name + ' is bijgewerkt.',
                })

            } catch (error) {
                console.log(error);
                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'Kan het product niet bijwerken'
                })
            }
        },

        async startCheckoutWithOrder() {
            this.checkoutPopup = true;
            this.ordersPopup = false;
            this.total = this.selectedOrder.total;

            {{--try {--}}
            {{--    let response = await fetch('{{ route('api.point-of-sale.insert-order-in-pos-cart') }}', {--}}
            {{--        method: 'POST',--}}
            {{--        headers: {--}}
            {{--            'Content-Type': 'application/json',--}}
            {{--            'Accept': 'application/json',--}}
            {{--        },--}}
            {{--        body: JSON.stringify({--}}
            {{--            posIdentifier: this.posIdentifier,--}}
            {{--            userId: this.userId,--}}
            {{--            order: this.selectedOrder,--}}
            {{--        })--}}
            {{--    });--}}

            {{--    let data = await response.json();--}}

            {{--    if (!response.ok) {--}}
            {{--        this.loading = false;--}}
            {{--        return $wire.dispatch('notify', {--}}
            {{--            type: 'danger',--}}
            {{--            message: data.message,--}}
            {{--        })--}}
            {{--    }--}}

            {{--    this.retrieveCart();--}}
            {{--    this.loading = false;--}}

            {{--    return $wire.dispatch('notify', {--}}
            {{--        type: 'success',--}}
            {{--        message: 'De bestelling is ingeladen.',--}}
            {{--    })--}}

            {{--} catch (error) {--}}
            {{--    console.log(error);--}}
            {{--    this.loading = false;--}}
            {{--    return $wire.dispatch('notify', {--}}
            {{--        type: 'danger',--}}
            {{--        message: 'Kan het product niet bijwerken'--}}
            {{--    })--}}
            {{--}--}}
        },

        async updateSelectedStockQuantity(quantity) {
            this.selectedStockProduct.actual_stock = quantity;
        },

        async refreshProducts() {
            this.loading = true;
            this.getAllProducts(true);
            this.loading = false;

            return $wire.dispatch('notify', {
                type: 'success',
                message: 'De producten zijn opgehaald',
            })
        },

        toggleFullscreen() {
            if (!document.fullscreenElement) {
                if (document.documentElement.requestFullscreen) {
                    document.documentElement.requestFullscreen();
                } else if (document.documentElement.mozRequestFullScreen) { // Firefox
                    document.documentElement.mozRequestFullScreen();
                } else if (document.documentElement.webkitRequestFullscreen) { // Chrome, Safari and Opera
                    document.documentElement.webkitRequestFullscreen();
                } else if (document.documentElement.msRequestFullscreen) { // IE/Edge
                    document.documentElement.msRequestFullscreen();
                }
                this.isFullscreen = true;
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.mozCancelFullScreen) { // Firefox
                    document.mozCancelFullScreen();
                } else if (document.webkitExitFullscreen) { // Chrome, Safari and Opera
                    document.webkitExitFullscreen();
                } else if (document.msExitFullscreen) { // IE/Edge
                    document.msExitFullscreen();
                }
                this.isFullscreen = false;
            }
        },

        showOrdersPopup() {
            this.loading = true;
            if (this.ordersPopup) {
                this.ordersPopup = false;
                this.focus();
            } else {
                this.ordersPopup = true;
                this.cancelOrderPopup = false;
                this.orderAmountToSkip = 0;
                this.retrieveOrders();
                this.focusSearchOrder();
            }
            this.loading = false;
        },

        hideOrdersPopup() {
            this.loading = true;
            this.ordersPopup = false;
            if (this.selectedOrder) {
                this.clearProducts();
            }
            this.selectedOrder = '';
            this.focus();
            this.loading = false;
        },

        hideCheckoutPopup() {
            this.loading = true;
            this.checkoutPopup = false;
            if (this.selectedOrder) {
                this.clearProducts();
            }
            this.selectedOrder = '';
            this.focus();
            this.loading = false;
        },

        showStockPopup() {
            this.loading = true;
            if (this.stockPopup) {
                this.stockPopup = false;
                this.focus();
            } else {
                this.stockPopup = true;
                this.searchStockProductQuery = '';
                this.selectedStockProduct = null;
                setTimeout(() => {
                    this.focusSearchProduct();
                }, 100);
            }
            this.loading = false;
        },

        selectOrder(order) {
            this.selectedOrder = order;
        },

        selectStockProduct(product) {
            this.selectedStockProduct = product;
        },

        changeRefundQuantity(quantityModel, quantity, maxQuantity) {
            quantityModel.refundQuantity = quantity;
            if (quantityModel.refundQuantity > maxQuantity) {
                quantityModel.refundQuantity = maxQuantity;
            }
            if (quantityModel.refundQuantity < 0) {
                quantityModel.refundQuantity = 0;
            }
        },

        focus() {
            document.getElementById("search-product-query").focus();
        },

        focusSearchOrder() {
            document.getElementById("search-order-query").focus();
        },

        focusSearchProduct() {
            document.getElementById("search-stock-product-query").focus();
        },

        launchEmojis(el) {
            const emojis = el.dataset.emojis.split(/[\s,]+/).filter(Boolean)
            const centerX = window.innerWidth / 2
            const centerY = window.innerHeight / 2

            for (let i = 0; i < 40; i++) {
                const emoji = emojis[Math.floor(Math.random() * emojis.length)]
                const element = document.createElement('div')
                element.textContent = emoji
                element.style.position = 'fixed'
                element.style.left = `${centerX}px`
                element.style.top = `${centerY}px`
                element.style.fontSize = `${Math.random() * 1.5 + 1.2}rem`
                element.style.pointerEvents = 'none'
                element.style.zIndex = 9999
                element.style.userSelect = 'none'

                document.body.appendChild(element)

                // random richting en afstand
                const angle = Math.random() * Math.PI * 2
                const distance = Math.random() * 600 + 150
                const targetX = Math.cos(angle) * distance
                const targetY = Math.sin(angle) * distance

                // random rotatie
                const rotation = (Math.random() - 0.5) * 720

                const animation = element.animate(
                    [
                        {transform: 'translate(0, 0) scale(0.8) rotate(0deg)', opacity: 1},
                        {
                            transform: `translate(${targetX}px, ${targetY}px) scale(1.4) rotate(${rotation}deg)`,
                            opacity: 0
                        }
                    ],
                    {
                        duration: 1500 + Math.random() * 1000,
                        easing: 'cubic-bezier(0.2, 0.8, 0.3, 1)',
                        fill: 'forwards',
                    }
                )

                animation.onfinish = () => element.remove()
            }
        },

        init() {
            $wire.on('toggle', (variable) => {
                this.toggle(variable[0]);
            })

            $wire.on('discountCodeCreated', () => {
                // De kortingscode is server-side al toegepast (submitCreateDiscountForm);
                // hier alleen de mand verversen, niet opnieuw toepassen.
                this.createDiscountPopup = false;
                this.focus();
                this.retrieveCart();
            })

            $wire.on('giftCardApplied', () => {
                this.redeemGiftCardPopup = false;
                this.focus();
                this.retrieveCart();
            })

            $wire.on('giftCardRemoved', () => {
                this.focus();
                this.retrieveCart();
            })

            $wire.on('discountCodeApplied', () => {
                this.focus();
                this.retrieveCart();
            })

            $wire.on('discountCodeRemoved', () => {
                this.focus();
                this.retrieveCart();
            })

            $wire.on('saveCustomerData', (variable) => {
                this.focus();
                this.saveCustomerData();
            })

            $wire.on('productChanged', (variable) => {
                this.focus();
                this.toggle('changeProductPricePopup');
                this.retrieveCart();
            })

            this.initialize();
            this.getAllProducts();

            this.$watch('searchProductQuery', (value) => {
                if (value.length >= 2) {
                    this.getSearchedProducts();
                } else {
                    this.searchedProducts = [];
                }
            });

            this.$watch('searchStockProductQuery', (value) => {
                if (value.length >= 2) {
                    this.selectedStockProduct = null;
                    this.getSearchedStockProducts();
                } else {
                    this.searchedStockProducts = [];
                    this.selectedStockProduct = null;
                }
            });

            this.$watch('searchOrderQuery', (value, oldValue) => {
                this.orderAmountToSkip = 0;
                this.retrieveOrders();
            });

            this.updateTime();

            setInterval(() => this.updateTime(), 1000)

            const observer = new IntersectionObserver(
                (entries) => {
                    entries.forEach((entry) => {
                        if (!entry.isIntersecting) return

                        // bijna onderaan → meer orders ophalen
                        this.retrieveOrders(true)
                    })
                },
                {
                    root: this.$refs.scrollContainer, // scrollende div
                    rootMargin: '0px 0px 200px 0px', // 200px vóór je echte bodem
                    threshold: 0,
                }
            )

            observer.observe(this.$refs.sentinel)
        }
    }))
    ;
</script>
@endscript
