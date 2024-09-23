<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

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

        html {
            touch-action: manipulation;
        }
    </style>
</head>

<body class="font-sans antialiased min-h-dvh bg-black text-white overflow-hidden">
{{--<body class="font-sans antialiased min-h-dvh bg-black text-white overflow-y-hidden">--}}
<div class="fixed h-full w-full inset-0 items-center justify-center overflow-hidden">
    <livewire:point-of-sale/>
</div>
@livewire('notifications')
@filamentScripts
{{--<script>--}}
{{--    function setViewportHeight() {--}}
{{--        // Check if the device is an iPad--}}
{{--        const isIpad = /iPad|Macintosh/.test(navigator.userAgent) && 'ontouchend' in document;--}}

{{--        let vh;--}}
{{--        if (isIpad) {--}}
{{--            // Set a lower height for iPads (e.g., 90% of the viewport height)--}}
{{--            vh = window.innerHeight * 0.90 * 0.01;--}}
{{--        } else {--}}
{{--            // Default for other devices--}}
{{--            vh = window.innerHeight * 0.01;--}}
{{--        }--}}

{{--        element.style.height = `${viewportHeight}px`;--}}
{{--    }--}}

{{--    // Set the height on page load--}}
{{--    setViewportHeight();--}}

{{--    // Recalculate on window resize--}}
{{--    window.addEventListener('resize', setViewportHeight);--}}
{{--</script>--}}
</body>
</html>

