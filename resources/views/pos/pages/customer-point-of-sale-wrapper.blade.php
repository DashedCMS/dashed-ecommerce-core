<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <title>Point of Sale - {{ Customsetting::get('company_name') }}</title>

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>

    @php(\Filament\Support\Facades\FilamentColor::register(collect(filament()->getPanels())->first()->getColors()))

    @filamentStyles

    {{ filament()->getTheme()->getHtml() }}
    {{ filament()->getFontHtml() }}

    <style>
        :root {
            --font-family: '{!! filament()->getFontFamily() !!}';
            --sidebar-width: {{ filament()->getSidebarWidth() }};
            --collapsed-sidebar-width: {{ filament()->getCollapsedSidebarWidth() }};
            --default-theme-mode: {{ filament()->getDefaultThemeMode()->value }};
        }

        html, body {
            margin: 0;
            padding: 0;
            /*overflow: hidden;*/
            width: 100%; /* Full width */
        }
    </style>

    <meta name="mobile-web-app-capable" content="yes">
    <link rel="manifest" href="/manifest.json">
</head>

<body class="font-sans antialiased bg-black text-white h-screen">
<livewire:customer-point-of-sale/>
@livewire('notifications')
@filamentScripts
<script>
    function adjustBodyHeight() {
        const viewportHeight = window.visualViewport ? window.visualViewport.height : window.innerHeight;

        // Subtract 150px from the viewport height
        const adjustedHeight = viewportHeight;

        // Apply the height to the body or a wrapper element
        document.body.style.height = `${adjustedHeight}px`;

        console.log(`Adjusted body height: ${adjustedHeight}px`);
    }

    // Detect if the device is a mobile or tablet
    function isMobileOrTablet() {
        return /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
    }

    // Adjust the height on load if it's a mobile or tablet
    if (isMobileOrTablet()) {
        window.addEventListener('load', adjustBodyHeight);
        window.addEventListener('resize', adjustBodyHeight); // Handle orientation changes
    }
</script>
</body>
</html>

