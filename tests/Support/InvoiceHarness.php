<?php

use Illuminate\Support\Facades\Storage;

if (! function_exists('bootInvoiceHarness')) {
    /**
     * Zet het pakketharnas klaar voor tests die een creditfactuur renderen
     * (markAsCancelledWithCredit() -> createInvoice()). De factuurview gebruikt
     * de globale aliassen `Translation`, `Customsetting`, `Sites` en `CurrencyHelper`, die het harnas niet registreert. De PDF wordt gestubd en opslag
     * wordt gefaked zodat er nooit iets naar een echte disk gaat.
     */
    function bootInvoiceHarness(): void
    {
        foreach ([
            'Translation' => \Dashed\DashedTranslations\Models\Translation::class,
            'Customsetting' => \Dashed\DashedCore\Models\Customsetting::class,
            'Sites' => \Dashed\DashedCore\Classes\Sites::class,
            'CurrencyHelper' => \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::class,
        ] as $alias => $class) {
            if (! class_exists($alias)) {
                class_alias($class, $alias);
            }
        }

        // De dompdf-serviceprovider draait niet in het harnas: een stub levert
        // de PDF-bytes, de view zelf wordt wel gerenderd.
        app()->bind('dompdf.wrapper', fn () => new class () {
            public function loadHTML(string $html): static
            {
                return $this;
            }

            public function output(): string
            {
                return '%PDF-stub';
            }
        });

        Storage::fake('dashed');
        Storage::fake('local');
        Storage::fake('public');
    }
}
