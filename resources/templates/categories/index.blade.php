<x-master>
    @if($productCategory)
        <x-blocks.breadcrumbs :breadcrumbs="$productCategory->breadcrumbs()"/>
        <x-blocks.all-products/>
        <x-blocks :content="$productCategory->content"></x-blocks>
    @else
        <x-blocks.all-categories :productCategories="$childProductCategories"/>
    @endif
</x-master>
