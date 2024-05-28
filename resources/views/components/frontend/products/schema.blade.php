<div itemscope itemtype="http://schema.org/Product">
    {{--        <meta itemprop="brand" content="facebook">--}}
    <meta itemprop="name" content="{{$product->name}}">
    <meta itemprop="description" content="{{strip_tags($product->short_description)}}">
    <meta itemprop="productID" content="{{$product->id}}">
    <meta itemprop="url" content="{{$product->getUrl()}}">
    <meta itemprop="sku" content="{{$product->sku}}">
    <meta itemprop="gtin8" content="{{$product->ean}}">
    @if($product->firstImageUrl)
        <meta itemprop="image" content="{{url($product->firstImageUrl)}}">
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
    </div>
</div>

<script>
    document.getElementById('add-to-cart-product-{{ $product->id }}') ? document.getElementById('add-to-cart-product-{{ $product->id }}').addEventListener('click', function() {
        dataLayer.push({
            'event': 'addToCart',
            'ecommerce': {
                'currencyCode': 'EUR',
                'add': {
                    'products': [{
                        'name': '{{ $product->name }}',
                        'id': '{{ $product->id }}',
                        'price': {{ number_format($product->currentPrice, 2, '.', '') }},
                        'quantity': 1
                    }]
                }
            }
        });
    }) : null;
</script>
