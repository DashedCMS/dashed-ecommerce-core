{{-- Fallback-layout voor <x-checkout-master>.

     dashed-ecommerce-core wikkelt de proforma-checkout (resources/views/proforma/checkout.blade.php)
     in <x-checkout-master>. Dat component is projectspecifiek: sites die een "clean" checkout-layout
     willen, zetten hun eigen resources/views/components/checkout-master.blade.php neer. Sites die dat
     niet doen, lieten `php artisan view:cache` klappen met "Unable to locate a class or view for
     component [checkout-master]", waardoor de deploy faalde.

     Deze fallback wordt geregistreerd via Blade::anonymousComponentPath() in
     DashedEcommerceCoreServiceProvider::bootingPackage(). Blade kijkt eerst in
     resources/views/components van het project, dus een eigen checkout-master blijft altijd winnen. --}}
<x-master>
    {{ $slot }}
</x-master>
