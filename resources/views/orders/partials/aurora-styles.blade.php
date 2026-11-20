{{--
    Paginaopmaak van de bestelpagina (Aurora). De kaarten, badges, stappen en
    rijen zelf komen uit de Aurora-componenten in dashed-core; dit blok regelt
    alleen de indeling eromheen en de regels binnen een kaart. Eigen klassen
    (au-od-) omdat niet elk klantproject vendor/dashed in Tailwind scant. Kleuren
    lezen de Aurora-variabelen en vallen terug op neutrale waarden.
    Staat buiten de Livewire-componenten: een stijlblok binnen een component
    verdwijnt bij de volgende morph.
--}}
<style>
    .au-od { display: flex; flex-direction: column; gap: 1rem; font-size: .875rem; line-height: 1.25rem; }
    .au-od-head { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
    .au-od-grid { display: grid; gap: 1rem; align-items: start; }
    .au-od-col { display: flex; flex-direction: column; gap: 1rem; min-width: 0; }
    .au-od-pair { display: grid; gap: 1rem; align-items: stretch; }
    @media (min-width: 768px) { .au-od-pair { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (min-width: 1024px) { .au-od-grid { grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); } }
    /* Mobiel: één kolom en de snelle acties bovenaan, voor de producten. */
    @media (max-width: 1023.98px) {
        .au-od-grid > .au-od-col { display: contents; }
        .au-od-grid > .au-od-col > .au-od-first-on-mobile { order: -1; }
    }

    .au-od-row { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
    .au-od-muted { color: var(--au-muted, inherit); }
    .au-od-small { font-size: .8125rem; }
    .au-od-label { font-size: .6875rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
    .au-od-strong { font-weight: 600; }
    .au-od-strike { text-decoration: line-through; }
    .au-od-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .8125rem; overflow-wrap: anywhere; }
    .au-od-gap-top { margin-top: .25rem; }
    .au-od-link { color: var(--au-mint, rgb(var(--primary-600, 5 150 105))); font-weight: 600; text-decoration: none; }
    .au-od-link:hover { text-decoration: underline; }
    .au-od-link:focus-visible { outline: 2px solid var(--au-mint, currentColor); outline-offset: 2px; border-radius: 4px; }
    .au-od-tab:focus-visible { outline: 2px solid var(--au-accent-solid, currentColor); outline-offset: 2px; }

    .au-od-list { list-style: none; margin: 0; padding: 0; }
    .au-od-line { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1rem; padding: .75rem 0; border-top: 1px solid var(--au-line, rgba(128, 128, 128, .2)); }
    .au-od-line:first-child { border-top: 0; padding-top: 0; }
    .au-od-line:last-child { padding-bottom: 0; }
    .au-od-grow { flex: 1 1 12rem; min-width: 0; overflow-wrap: anywhere; }
    .au-od-thumb { width: 3.5rem; height: 3.5rem; object-fit: cover; border-radius: .625rem; flex: none; background: var(--au-panel-2, rgba(128, 128, 128, .1)); }
    .au-od-qty { min-width: 2.5rem; text-align: right; }
    .au-od-price { min-width: 5.5rem; text-align: right; }

    .au-od-products { display: grid; gap: 1.25rem; }
    @media (min-width: 1280px) { .au-od-products { grid-template-columns: minmax(0, 1fr) minmax(14rem, 17rem); align-items: start; } }
    .au-od-totals { display: flex; flex-direction: column; gap: .5rem; margin: 0; }
    .au-od-total-row { display: flex; justify-content: space-between; gap: 1rem; }
    .au-od-total-row dd { margin: 0; text-align: right; }
    .au-od-total-row--total { font-size: 1.0625rem; font-weight: 700; padding-top: .625rem; border-top: 1px solid var(--au-line, rgba(128, 128, 128, .2)); }

    .au-od-banner { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; padding: .875rem 1.125rem; border-radius: 14px; border: 1px solid var(--au-amber, #f59e0b); background: color-mix(in srgb, var(--au-amber, #f59e0b) 12%, transparent); }
    .au-od-parcel { display: inline-flex; align-items: center; gap: .5rem; padding: .375rem .75rem .375rem .5rem; border-radius: 9px; color: #fff; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; font-size: .875rem; }
    .au-od-parcel svg { width: 1.375rem; height: 1.375rem; }

    .au-od-quick { display: flex; flex-direction: column; gap: .5rem; }
    .au-od-quick > div, .au-od-quick .w-full, .au-od-quick .fi-btn { width: 100%; }
    .au-od-quick .fi-btn { justify-content: center; }
    .au-od-actions { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
    .au-od-actions > div { width: auto; }

    .au-od-customer { display: flex; align-items: center; gap: .875rem; }
    .au-od-avatar { width: 3rem; height: 3rem; border-radius: 12px; object-fit: cover; flex: none; }
    .au-od-contact { display: flex; flex-direction: column; gap: .625rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--au-line, rgba(128, 128, 128, .2)); }
    .au-od-contact-item { display: flex; align-items: flex-start; gap: .625rem; overflow-wrap: anywhere; }
    .au-od-contact-item svg { width: 1.125rem; height: 1.125rem; flex: none; margin-top: .0625rem; color: var(--au-muted, inherit); }
    .au-od-address { font-style: normal; }

    .au-od-tabs { display: inline-flex; gap: .25rem; padding: .25rem; border-radius: 9px; border: 1px solid var(--au-line, rgba(128, 128, 128, .25)); }
    .au-od-tab { padding: .25rem .625rem; border-radius: 6px; font-size: .75rem; font-weight: 600; cursor: pointer; background: transparent; color: var(--au-muted, inherit); border: 0; }
    .au-od-tab--on { background: var(--au-mint, rgb(var(--primary-500, 16 185 129))); color: var(--au-on-mint, #04140f); }
    .au-od-timeline .au-od-line { align-items: flex-start; flex-wrap: nowrap; }
    .au-od-dot { width: .625rem; height: .625rem; margin-top: .3125rem; border-radius: 9999px; flex: none; border: 2px solid var(--au-mint, #10b981); }
    .au-od-dot--mail { border-color: var(--au-blue, #3b82f6); }
    .au-od-dot--note { border-color: var(--au-purple, #a855f7); }
    .au-od-when { flex: none; text-align: right; white-space: nowrap; }

    .au-od-doc { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
    .au-od-preview { display: grid; gap: 1rem; margin-top: 1rem; }
    @media (min-width: 1024px) { .au-od-preview { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .au-od-preview iframe { width: 100%; height: 28rem; border: 0; border-radius: 9px; background: #fff; }
    .au-od-summary { cursor: pointer; font-weight: 600; }
</style>
