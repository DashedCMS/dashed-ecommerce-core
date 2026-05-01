<div data-cart-suggestions-view="cart">
  @foreach ($suggestions as $product)
    <div data-product-id="{{ $product->id }}">{{ $product->name }} — €{{ $product->current_price }}</div>
  @endforeach
</div>
