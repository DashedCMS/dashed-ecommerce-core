{{--
    POS LAYOUT VOORSTEL — visuele mockup met dummy data.
    Dit bestand wijzigt de bestaande point-of-sale.blade.php NIET.
    Doel: alleen de nieuwe layout/hiërarchie beoordelen. Geen Alpine/API-logica.

    Bekijken: registreer tijdelijk een route die deze view rendert, bv.
        Route::get('/admin/pos-voorstel', fn () => view('dashed-ecommerce-core::pos.pages.point-of-sale-proposal'));
    of open de los meegestuurde .html in de browser.

    Toggle rechtsboven schakelt tussen het LICHT- en DONKER-voorstel.
--}}
<!DOCTYPE html>
<html lang="nl" class="theme-dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>POS layout-voorstel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50:'#eef7f1',100:'#d6ecdf',200:'#aedabf',300:'#7cc198',
                            400:'#4ba673',500:'#2f8d59',600:'#227045',700:'#1c5838',
                            800:'#17452d',900:'#123524',950:'#0a2016',
                        },
                    },
                    fontFamily: { sans: ['Inter','ui-sans-serif','system-ui','sans-serif'] },
                },
            },
        };
    </script>
    <style>
        /* Thema-variabelen — de hele layout leest hieruit, zodat dezelfde
           markup zowel licht als donker correct rendert. */
        .theme-dark {
            --bg:#0b0f0d; --panel:#11161300; --card:#161d19; --card-2:#1d2620;
            --line:#26332b; --text:#eef2ef; --muted:#9bb0a4; --chip:#1d2620;
            --chip-active:#2f8d59;
        }
        .theme-light {
            --bg:#f3f5f4; --panel:#ffffff; --card:#ffffff; --card-2:#f6f8f7;
            --line:#e2e8e4; --text:#0f1a14; --muted:#5d7064; --chip:#eef2f0;
            --chip-active:#2f8d59;
        }
        body { background:var(--bg); color:var(--text); }
        .panel { background:var(--panel); }
        .card  { background:var(--card);  border-color:var(--line); }
        .card2 { background:var(--card-2); border-color:var(--line); }
        .ln    { border-color:var(--line); }
        .muted { color:var(--muted); }
        .chip  { background:var(--chip); }
        ::-webkit-scrollbar { width:10px; height:10px; }
        ::-webkit-scrollbar-thumb { background:var(--line); border-radius:9999px; }
        [x-cloak]{display:none}
    </style>
</head>
<body class="font-sans antialiased h-screen overflow-hidden select-none">

<div class="h-screen flex flex-col">

    {{-- ===================== TOPBAR ===================== --}}
    <header class="panel border-b ln flex items-center justify-between px-5 h-16 shrink-0">
        <div class="flex items-center gap-4">
            <div class="h-9 w-9 rounded-lg bg-brand-500 grid place-items-center text-white font-black">D</div>
            <div class="leading-tight">
                <p class="font-bold text-lg">Dashed Store</p>
                <p class="muted text-xs">Kassa 1 · Kassasessie geopend 09:00</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <div class="hidden md:flex items-center gap-2 mr-2 px-3 h-9 rounded-lg chip text-sm">
                <span class="h-2 w-2 rounded-full bg-brand-500"></span>
                <span class="muted">Medewerker</span>
                <span class="font-semibold">Robin</span>
            </div>
            <div class="px-3 h-9 rounded-lg chip grid place-items-center font-mono font-semibold tabular-nums">14:32</div>

            {{-- thema-toggle: wisselt het hele voorstel tussen licht en donker --}}
            <button onclick="toggleTheme()" id="themeBtn"
                    class="px-3 h-9 rounded-lg chip flex items-center gap-2 text-sm font-medium hover:opacity-80">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                <span id="themeLabel">Licht</span>
            </button>

            <button class="size-9 rounded-lg chip grid place-items-center hover:opacity-80" title="Volledig scherm">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3H5a2 2 0 0 0-2 2v3M21 8V5a2 2 0 0 0-2-2h-3M16 21h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>
            </button>
        </div>
    </header>

    {{-- ===================== HOOFDLAYOUT ===================== --}}
    <div class="flex-1 min-h-0 flex">

        {{-- ---------- LINKS: catalogus ---------- --}}
        <section class="flex-1 min-w-0 flex flex-col p-5 gap-4">

            {{-- zoekbalk + compacte actie-toolbar (vervangt de muur van grote knoppen) --}}
            <div class="flex items-center gap-3">
                <div class="relative flex-1">
                    <svg class="size-5 muted absolute left-3.5 top-1/2 -translate-y-1/2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="text" placeholder="Zoek product, scan barcode…"
                           class="card border w-full h-12 rounded-xl pl-11 pr-4 text-base outline-none focus:ring-2 focus:ring-brand-500 placeholder:muted"
                           style="color:var(--text)">
                </div>
                <div class="flex items-center gap-2">
                    @php
                        $tools = [
                            ['Korting','M9 9h.01M15 15h.01M16 8 8 16'],
                            ['Cadeaubon','M20 12v9H4v-9M2 7h20v5H2zM12 22V7M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z'],
                            ['Los product','M12 5v14M5 12h14'],
                            ['Klant','M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z'],
                            ['Parkeren','M9 17V7h4a3 3 0 0 1 0 6H9'],
                        ];
                    @endphp
                    @foreach($tools as $t)
                        <button class="card2 border h-12 px-3.5 rounded-xl flex items-center gap-2 text-sm font-medium hover:border-brand-500 transition-colors">
                            <svg class="size-4 text-brand-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="{{ $t[1] }}"/></svg>
                            <span class="hidden lg:inline">{{ $t[0] }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- categorie-chips --}}
            <div class="flex items-center gap-2 overflow-x-auto pb-1">
                @php $cats = ['Alles'=>true,'Koffie'=>false,'Thee'=>false,'Gebak'=>false,'Brood'=>false,'Lunch'=>false,'Cadeau'=>false,'Overig'=>false]; @endphp
                @foreach($cats as $cat => $active)
                    <button class="shrink-0 h-9 px-4 rounded-full text-sm font-semibold transition-colors {{ $active ? 'bg-brand-500 text-white' : 'chip muted hover:opacity-80' }}">{{ $cat }}</button>
                @endforeach
            </div>

            {{-- productrooster --}}
            <div class="flex-1 min-h-0 overflow-y-auto -mx-1 px-1">
                <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-3">
                    @php
                        $products = [
                            ['Cappuccino','€ 3,50','Op voorraad','ok','☕'],
                            ['Espresso','€ 2,80','Op voorraad','ok','☕'],
                            ['Verse muntthee','€ 3,20','Op voorraad','ok','🍵'],
                            ['Appeltaart punt','€ 4,25','Nog 6','low','🥧'],
                            ['Croissant','€ 2,40','Op voorraad','ok','🥐'],
                            ['Brownie','€ 3,75','Op voorraad','ok','🍫'],
                            ['Tosti ham/kaas','€ 5,50','Op voorraad','ok','🥪'],
                            ['Verse jus','€ 3,90','Nog 3','low','🍊'],
                            ['Bruiswater','€ 2,20','Op voorraad','ok','💧'],
                            ['Latte macchiato','€ 3,80','Op voorraad','ok','☕'],
                            ['Carrot cake','€ 4,50','Uitverkocht','out','🥕'],
                            ['Clubsandwich','€ 7,95','Op voorraad','ok','🥪'],
                            ['Chai latte','€ 3,95','Op voorraad','ok','🫖'],
                            ['Scone','€ 2,95','Op voorraad','ok','🧁'],
                            ['Cadeaubon €25','€ 25,00','Voorraad ∞','ok','🎁'],
                        ];
                        $stockClass = ['ok'=>'text-brand-500','low'=>'text-amber-500','out'=>'text-red-500'];
                    @endphp
                    @foreach($products as $p)
                        <button class="card border rounded-2xl p-3 text-left flex flex-col gap-3 hover:border-brand-500 hover:-translate-y-0.5 transition-all {{ $p[3]==='out' ? 'opacity-50' : '' }}">
                            <div class="card2 border rounded-xl aspect-[4/3] grid place-items-center text-4xl">{{ $p[4] }}</div>
                            <div class="leading-tight">
                                <p class="font-semibold text-sm line-clamp-2">{{ $p[0] }}</p>
                                <p class="font-bold text-base mt-1">{{ $p[1] }}</p>
                                <p class="text-xs font-medium mt-0.5 {{ $stockClass[$p[3]] }}">{{ $p[2] }}</p>
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ---------- RECHTS: bestelling / afrekenen ---------- --}}
        <aside class="w-[400px] shrink-0 panel border-l ln flex flex-col">

            {{-- kop --}}
            <div class="flex items-center justify-between px-5 h-16 border-b ln shrink-0">
                <div class="flex items-center gap-2">
                    <svg class="size-5 text-brand-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
                    <p class="font-bold text-lg">Bestelling</p>
                    <span class="chip muted text-xs font-semibold px-2 py-0.5 rounded-full">4 items</span>
                </div>
                <button class="text-sm muted hover:text-red-500 font-medium flex items-center gap-1">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    Leegmaken
                </button>
            </div>

            {{-- klantregel --}}
            <button class="flex items-center gap-3 px-5 h-14 border-b ln hover:bg-brand-500/5 transition-colors text-left">
                <div class="size-8 rounded-full bg-brand-500/15 text-brand-500 grid place-items-center">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <div class="flex-1 leading-tight">
                    <p class="text-sm font-semibold">Anita de Vries</p>
                    <p class="muted text-xs">Spaarpunten: 240 · klant gekoppeld</p>
                </div>
                <svg class="size-4 muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
            </button>

            {{-- regels --}}
            <div class="flex-1 min-h-0 overflow-y-auto px-3 py-3 space-y-2">
                @php
                    $lines = [
                        ['Cappuccino','€ 3,50',2,'☕'],
                        ['Appeltaart punt','€ 4,25',1,'🥧'],
                        ['Clubsandwich','€ 7,95',1,'🥪'],
                        ['Verse jus','€ 3,90',2,'🍊'],
                    ];
                @endphp
                @foreach($lines as $l)
                    <div class="card border rounded-xl p-2.5 flex items-center gap-3">
                        <div class="card2 border rounded-lg size-12 grid place-items-center text-2xl shrink-0">{{ $l[3] }}</div>
                        <div class="flex-1 min-w-0 leading-tight">
                            <p class="font-semibold text-sm truncate">{{ $l[0] }}</p>
                            <p class="muted text-xs">{{ $l[1] }} per stuk</p>
                        </div>
                        <div class="flex items-center gap-1 chip rounded-lg p-1">
                            <button class="size-7 rounded-md grid place-items-center hover:bg-brand-500 hover:text-white transition-colors">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/></svg>
                            </button>
                            <span class="w-6 text-center font-bold tabular-nums">{{ $l[2] }}</span>
                            <button class="size-7 rounded-md grid place-items-center hover:bg-brand-500 hover:text-white transition-colors">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- samenvatting + betaling + afrekenknop (sticky onderaan) --}}
            <div class="border-t ln p-5 space-y-4 shrink-0">
                <div class="space-y-1.5 text-sm">
                    <div class="flex justify-between muted"><span>Subtotaal</span><span class="tabular-nums" style="color:var(--text)">€ 27,00</span></div>
                    <div class="flex justify-between muted"><span>Korting (10%)</span><span class="tabular-nums text-brand-500">− € 2,70</span></div>
                    <div class="flex justify-between muted"><span>Btw 9%</span><span class="tabular-nums" style="color:var(--text)">€ 2,01</span></div>
                    <div class="flex justify-between items-center pt-2 mt-1 border-t ln">
                        <span class="font-bold text-base">Totaal</span>
                        <span class="font-black text-2xl tabular-nums">€ 24,30</span>
                    </div>
                </div>

                {{-- betaalmethode --}}
                <div class="grid grid-cols-3 gap-2">
                    @php $pays = [['PIN','M2 7h20v10H2zM2 11h20',true],['Contant','M2 6h20v12H2zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6',false],['Online','M12 2v20M2 12h20',false]]; @endphp
                    @foreach($pays as $pay)
                        <button class="border rounded-xl py-2.5 flex flex-col items-center gap-1 text-xs font-semibold transition-colors {{ $pay[2] ? 'bg-brand-500 text-white border-brand-500' : 'card2 ln hover:border-brand-500' }}">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="{{ $pay[1] }}"/></svg>
                            {{ $pay[0] }}
                        </button>
                    @endforeach
                </div>

                <button class="w-full h-14 rounded-xl bg-brand-500 hover:bg-brand-600 text-white font-bold text-lg flex items-center justify-center gap-3 transition-colors shadow-lg shadow-brand-500/20">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg>
                    Afrekenen · € 24,30
                </button>
            </div>
        </aside>
    </div>
</div>

<script>
    function toggleTheme() {
        const html = document.documentElement;
        const dark = html.classList.contains('theme-dark');
        html.classList.toggle('theme-dark', !dark);
        html.classList.toggle('theme-light', dark);
        document.getElementById('themeLabel').textContent = dark ? 'Donker' : 'Licht';
    }
</script>
</body>
</html>
