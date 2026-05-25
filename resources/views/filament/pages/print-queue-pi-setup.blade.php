@php
    $printerListUrl = class_exists(\Dashed\DashedEcommerceCore\Filament\Resources\PrinterResource::class)
        ? \Dashed\DashedEcommerceCore\Filament\Resources\PrinterResource::getUrl('index')
        : null;
@endphp

<div style="font-size: 0.875rem; line-height: 1.5; display: flex; flex-direction: column; gap: 1.5rem;">

    <div style="background-color: #d1fae5; border-left: 4px solid #059669; border-radius: 0.5rem; padding: 1rem; color: #064e3b;">
        <strong>Per printer is er een eigen installatie-handleiding met &eacute;&eacute;n-klik install commando.</strong>
        <p style="margin-top: 0.5rem;">
            @if ($printerListUrl)
                Ga naar <a href="{{ $printerListUrl }}" style="text-decoration: underline; font-weight: 600; color: inherit;">Print queue &rarr; Printers</a>,
            @else
                Ga naar Print queue &rarr; Printers,
            @endif
            kies (of maak) een printer, klik op "Genereer token" en kopieer het oneliner-commando dat verschijnt. Dat is de snelste route voor een leek met een Pi.
        </p>
    </div>

    <div>
        <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">Wat doet het install script?</h3>
        <p style="margin-bottom: 0.5rem; color: #4b5563;">
            Het script dat de signed URL teruggeeft (Bash) doet automatisch het volgende op de Raspberry Pi:
        </p>
        <ol style="list-style-type: decimal; list-style-position: inside; color: #4b5563; margin-bottom: 0.5rem;">
            <li>Installeert CUPS, Python en de Python-libraries (requests, pyyaml).</li>
            <li>Detecteert de aangesloten USB-printer via <code>lpinfo</code>.</li>
            <li>Registreert de printer in CUPS onder een naam die afgeleid is van de printer-naam in dit CMS.</li>
            <li>Downloadt de daemon (<code>print_daemon.py</code>) en het systemd-unit-bestand.</li>
            <li>Schrijft <code>/opt/dashedcms-printer/config.yaml</code> met je token, API-URL en CUPS-naam al ingevuld.</li>
            <li>Start de service en zet hem op auto-start bij boot.</li>
        </ol>
        <p style="color: #4b5563;">
            De URL waar het script vandaan komt is een signed URL (geldig 2 uur na openen van de printer-pagina), dus niet raadbaar van buitenaf zonder de admin-pagina te kunnen openen.
        </p>
    </div>

    <div>
        <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">Wat heb je nodig?</h3>
        <ul style="list-style-type: disc; list-style-position: inside; color: #4b5563;">
            <li>Een Raspberry Pi met Raspberry Pi OS (Debian-based), netwerk-toegang naar dit CMS.</li>
            <li>SSH-toegang naar de Pi (standaard gebruiker <code>pi</code>).</li>
            <li>Een USB-printer aangesloten v&oacute;&oacute;rdat je het script draait.</li>
            <li>Sudo-rechten op de Pi (het script begint met <code>sudo bash</code>).</li>
        </ul>
    </div>

    <div style="background-color: #f3f4f6; border-radius: 0.5rem; padding: 1rem; color: #374151;">
        <strong>Verificatie en troubleshooting</strong>
        <ul style="list-style-type: disc; list-style-position: inside; margin-top: 0.25rem;">
            <li>Binnen 30 seconden na het draaien van het script moet de printer-status in het CMS naar "online" springen.</li>
            <li>Live logs bekijken op de Pi: <code>sudo journalctl -u dashedcms-printer -f</code></li>
            <li>"401 Unauthorized" in logs: token is opnieuw gegenereerd; klik op de printer-pagina opnieuw op "Genereer token" en draai de oneliner opnieuw.</li>
            <li>Geen USB-printer gevonden: kabel los/aan en opnieuw. <code>lpstat -p</code> laat zien welke printers CUPS kent.</li>
            <li>De service draait maar print niets: <code>echo "test" | lp -d &lt;cups-naam&gt;</code> op de Pi om CUPS los te testen.</li>
        </ul>
    </div>
</div>
