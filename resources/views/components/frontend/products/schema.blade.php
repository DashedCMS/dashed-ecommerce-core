<div itemscope itemtype="http://schema.org/Product">
    {{--        <meta itemprop="brand" content="facebook">--}}
    <meta itemprop="name" content="{{$product->name}}">
    <meta itemprop="description"
          content="{{strip_tags((($product->short_description ?: ($product->productGroup->short_description ?? '')) ?: $product->description) ?: ($product->productGroup->description ?? ''))}}">
    <meta itemprop="productID" content="{{$product->id}}">
    <meta itemprop="url" content="{{$product->getUrl()}}">
    <meta itemprop="sku" content="{{$product->sku}}">
    <meta itemprop="gtin8" content="{{$product->ean}}">
    @if($product->firstImage)
        <meta itemprop="image" content="{{mediaHelper()->getSingleMedia($product->firstImage, 'original')->url ?? ''}}">
    @endif
    {{--    <div itemprop="value" itemscope itemtype="http://schema.org/PropertyValue">--}}
    {{--        <span itemprop="propertyID" content="item_group_id"></span>--}}
    {{--        <meta itemprop="value" content="fb_tshirts">--}}
    {{--    </div>--}}
    <div itemprop="offers" itemscope itemtype="http://schema.org/Offer">
        @if($product->inStock())
            <link itemprop="availability" href="http://schema.org/InStock">
        @else
            <link itemprop="availability" href="http://schema.org/OutOfStock">
        @endif
        <link itemprop="itemCondition" href="http://schema.org/NewCondition">
        <meta itemprop="price" content="{{number_format($product->currentPrice, 2, '.', '')}}">
        <meta itemprop="priceCurrency" content="EUR">
        <meta itemprop="url" content="{{$product->getUrl()}}">
        <meta itemprop="priceValidUntil" content="{{now()->addYear()}}">

        <div itemprop="hasMerchantReturnPolicy" itemscope itemtype="http://schema.org/MerchantReturnPolicy">
            <meta itemprop="returnPolicyCategory" content="http://schema.org/Returnable">
            <meta itemprop="merchantReturnDays" content="30">
            <meta itemprop="returnPolicySeasonalOverride" content="false">
            <meta itemprop="returnMethod" content="http://schema.org/ReturnByMail">
            <meta itemprop="returnFees" content="http://schema.org/FreeReturn">
            @foreach(\Dashed\DashedEcommerceCore\Classes\Countries::getAllSelectedCountryCodes() as $countryCode)
                <div itemprop="applicableCountry" itemscope itemtype="http://schema.org/Country">
                    <meta itemprop="name" content="{{ $countryCode }}">
                </div>
            @endforeach
        </div>

        @foreach(\Dashed\DashedEcommerceCore\Classes\Countries::getAllSelectedCountryCodes() as $countryCode)
            <div itemprop="shippingDetails" itemscope itemtype="http://schema.org/OfferShippingDetails">
                <div itemprop="shippingDestination" itemscope itemtype="http://schema.org/DefinedRegion">
                    <meta itemprop="addressCountry" content="NL">
                </div>
                <div itemprop="deliveryTime" itemscope itemtype="http://schema.org/ShippingDeliveryTime">
                    <div itemprop="handlingTime" itemscope itemtype="http://schema.org/QuantitativeValue">
                        <meta itemprop="minValue" content="0">
                        <meta itemprop="maxValue" content="0">
                        <meta itemprop="unitCode" content="d"> {{-- d = dagen --}}
                    </div>
                    <div itemprop="transitTime" itemscope itemtype="http://schema.org/QuantitativeValue">
                        <meta itemprop="minValue" content="1">
                        <meta itemprop="maxValue" content="1">
                        <meta itemprop="unitCode" content="d">
                    </div>
                </div>
                <link itemprop="transitTimeLabel" href="http://schema.org/StandardShipping">

                <div itemprop="eligibleTransactionVolume" itemscope itemtype="http://schema.org/PriceSpecification">
                    <meta itemprop="minPrice" content="99.01">
                    <meta itemprop="priceCurrency" content="EUR">
                </div>
                <div itemprop="shippingRate" itemscope itemtype="http://schema.org/MonetaryAmount">
                    <meta itemprop="value" content="0.00">
                    <meta itemprop="currency" content="EUR">
                </div>
            </div>

            <div itemprop="shippingDetails" itemscope itemtype="http://schema.org/OfferShippingDetails">
                <div itemprop="shippingDestination" itemscope itemtype="http://schema.org/DefinedRegion">
                    <meta itemprop="addressCountry" content="NL">
                </div>
                <div itemprop="deliveryTime" itemscope itemtype="http://schema.org/ShippingDeliveryTime">
                    <div itemprop="handlingTime" itemscope itemtype="http://schema.org/QuantitativeValue">
                        <meta itemprop="minValue" content="0">
                        <meta itemprop="maxValue" content="0">
                        <meta itemprop="unitCode" content="d"> {{-- d = dagen --}}
                    </div>
                    <div itemprop="transitTime" itemscope itemtype="http://schema.org/QuantitativeValue">
                        <meta itemprop="minValue" content="1">
                        <meta itemprop="maxValue" content="1">
                        <meta itemprop="unitCode" content="d">
                    </div>
                </div>
                <link itemprop="transitTimeLabel" href="http://schema.org/StandardShipping">

                <div itemprop="eligibleTransactionVolume" itemscope itemtype="http://schema.org/PriceSpecification">
                    <meta itemprop="maxPrice" content="99.00">
                    <meta itemprop="priceCurrency" content="EUR">
                </div>
                <div itemprop="shippingRate" itemscope itemtype="http://schema.org/MonetaryAmount">
                    <meta itemprop="value"
                          content="{{ Translation::get('max-shipping-price-for-' . $countryCode, 'shipping', '4.95') }}">
                    <meta itemprop="currency" content="EUR">
                </div>
            </div>

            <div itemprop="shippingDetails" itemscope itemtype="http://schema.org/OfferShippingDetails">
                <div itemprop="shippingDestination" itemscope itemtype="http://schema.org/DefinedRegion">
                    <meta itemprop="addressCountry" content="{{ $countryCode }}">
                </div>
                <meta itemprop="deliveryTime" content="P1D">
                <div itemprop="shippingRate" itemscope itemtype="http://schema.org/MonetaryAmount">
                    <meta itemprop="value" content="0.00">
                    <meta itemprop="maxValue"
                          content="{{ Translation::get('max-shipping-price-for-' . $countryCode, 'shipping', '4.95') }}">
                    <meta itemprop="currency" content="EUR">
                </div>
                <meta itemprop="shippingRateCurrency" content="EUR">
                <link itemprop="transitTimeLabel" href="http://schema.org/StandardShipping">
            </div>
        @endforeach
    </div>
</div>

@script
<script>
    $wire.on('productAddedToCart', (event) => {
        dataLayer.push({
            'event': 'addToCart',
            'ecommerce': {
                'currencyCode': 'EUR',
                'add': {
                    'products': [{
                        'name': event[0].productName,
                        'id': event[0].product.id,
                        'price': event[0].price,
                        'quantity': event[0].quantity,
                    }]
                }
            }
        });
    });
</script>
@endscript
