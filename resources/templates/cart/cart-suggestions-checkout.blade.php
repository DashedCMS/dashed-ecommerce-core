<div data-cart-suggestions-view="checkout">
  @foreach ($suggestions as $product)
    <div data-product-id="{{ $product->id }}">{{ $product->name }} — €{{ $product->current_price }}</div>
  @endforeach
</div>
