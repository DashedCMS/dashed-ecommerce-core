<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <title>Point of Sale - {{ Customsetting::get('site_name') }}</title>

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
<livewire:point-of-sale/>
@livewire('notifications')
@filamentScripts
</body>
</html>

