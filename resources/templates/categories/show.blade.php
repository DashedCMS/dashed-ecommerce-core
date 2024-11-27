<x-master>
    @if($productCategory)
        <x-blocks.breadcrumbs :breadcrumbs="$productCategory->breadcrumbs()"/>
    @endif
    <x-blocks.all-products/>
    @if($productCategory)
        <x-blocks :content="$productCategory->content"></x-blocks>
    @endif
</x-master>
