<div itemscope itemtype="http://schema.org/CollectionPage">
    @php($name = is_array($productCategory->name) ? reset($productCategory->name) : (string) $productCategory->name)
    <meta itemprop="name" content="{{ $name }}">
    @php($description = strip_tags((string) ($productCategory->metadata->description ?? $productCategory->description ?? '')))
    @if($description)
        <meta itemprop="description" content="{{ $description }}">
    @endif
    <meta itemprop="identifier" content="{{ $productCategory->id }}">
    <meta itemprop="url" content="{{ url($productCategory->getUrl()) }}">
    @if($productCategory->metadata && $productCategory->metadata->image)
        <meta itemprop="image" content="{{ mediaHelper()->getSingleMedia($productCategory->metadata->image, 'original')->url ?? '' }}">
    @elseif(isset($productCategory->image) && $productCategory->image)
        <meta itemprop="image" content="{{ mediaHelper()->getSingleMedia($productCategory->image, 'original')->url ?? '' }}">
    @endif
</div>
