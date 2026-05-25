@php
    $printerListUrl = class_exists(\Dashed\DashedEcommerceCore\Filament\Resources\PrinterResource::class)
        ? \Dashed\DashedEcommerceCore\Filament\Resources\PrinterResource::getUrl('index')
        : null;
@endphp

<div style="font-size: 0.875rem; line-height: 1.5; display: flex; flex-direction: column; gap: 1.5rem;">

    <div style="background-color: #eff6ff; border-left: 4px solid #2563eb; border-radius: 0.5rem; padding: 1rem; color: #1e3a8a;">
        <strong>Twee installatie-opties bovenaan deze pagina.</strong>
        <p style="margin-top: 0.5rem;">
            Klik op "Pair een nieuwe Raspberry Pi" en kies dan bij de pairing-sectie tussen Optie A (Pi/native Linux) of Optie B (NAS/Docker). Beide doen onder water hetzelfde.
        </p>
    </div>

    <div>
        <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">Optie A: Raspberry Pi of Linux server</h3>
        <p style="color: #4b5563;">
            Werkt op een Pi (Raspberry Pi OS) of elke Debian/Ubuntu server met systemd. Script installeert CUPS + Python via apt, detecteert USB-printers, registreert ze, pair't met dit CMS en start een systemd-service.
        </p>
    </div>

    <div>
        <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">Optie B: NAS of andere Linux-host met Docker</h3>
        <p style="margin-bottom: 0.75rem; color: #4b5563;">
            Werkt op Synology, QNAP, UnRAID, TrueNAS Scale, Asustor en elke Linux met Docker. Het script downloadt een Dockerfile + entrypoint, bouwt de container lokaal en start hem via docker compose. De container bundelt CUPS, Python en de daemon.
        </p>

        <h4 style="font-size: 0.9375rem; font-weight: 600; margin-bottom: 0.5rem; margin-top: 1rem;">Hoe open je een terminal op je NAS?</h4>

        <div style="display: flex; flex-direction: column; gap: 0.75rem;">

            <div style="background-color: #f9fafb; border-radius: 0.375rem; padding: 0.75rem; color: #374151;">
                <strong>Synology DSM</strong>
                <ol style="list-style-type: decimal; list-style-position: inside; margin-top: 0.25rem;">
                    <li>Open DSM in je browser, ga naar <strong>Pakketcentrum</strong> en installeer <strong>Container Manager</strong> (heette vroeger Docker) als je dat nog niet hebt.</li>
                    <li>Ga naar <strong>Configuratiescherm &rarr; Terminal en SNMP</strong>, vink <strong>SSH-service inschakelen</strong> aan, druk op Toepassen.</li>
                    <li>Open op je Mac/Windows Terminal of PuTTY en typ: <code>ssh &lt;jouw-admin-gebruiker&gt;@&lt;synology-ip&gt;</code></li>
                    <li>Voer je DSM admin wachtwoord in.</li>
                    <li>Word root: <code>sudo -i</code> (vraagt je wachtwoord opnieuw).</li>
                    <li>Plak het Optie B commando hierboven, druk Enter.</li>
                </ol>
            </div>

            <div style="background-color: #f9fafb; border-radius: 0.375rem; padding: 0.75rem; color: #374151;">
                <strong>QNAP QTS</strong>
                <ol style="list-style-type: decimal; list-style-position: inside; margin-top: 0.25rem;">
                    <li>Installeer <strong>Container Station</strong> uit het App Center.</li>
                    <li>Ga naar <strong>Configuratiescherm &rarr; Netwerk- en bestandsservices &rarr; Telnet/SSH</strong>, vink SSH aan.</li>
                    <li>SSH in: <code>ssh admin@&lt;qnap-ip&gt;</code> (default poort 22).</li>
                    <li>Plak het Optie B commando.</li>
                </ol>
            </div>

            <div style="background-color: #f9fafb; border-radius: 0.375rem; padding: 0.75rem; color: #374151;">
                <strong>UnRAID</strong>
                <ol style="list-style-type: decimal; list-style-position: inside; margin-top: 0.25rem;">
                    <li>Open de UnRAID web UI.</li>
                    <li>Klik rechts bovenin op het terminal-icoon (zwart vierkant met &gt;_) voor een web-shell.</li>
                    <li>Of SSH erin: <code>ssh root@&lt;unraid-ip&gt;</code></li>
                    <li>Plak het Optie B commando.</li>
                </ol>
            </div>

            <div style="background-color: #f9fafb; border-radius: 0.375rem; padding: 0.75rem; color: #374151;">
                <strong>TrueNAS Scale</strong>
                <ol style="list-style-type: decimal; list-style-position: inside; margin-top: 0.25rem;">
                    <li>Open TrueNAS in je browser.</li>
                    <li>Klik in het menu links op <strong>Shell</strong> (web-terminal in de browser).</li>
                    <li>Of SSH: <code>ssh root@&lt;truenas-ip&gt;</code> (eerst SSH-service activeren via <strong>System Settings &rarr; Services</strong>).</li>
                    <li>Plak het Optie B commando.</li>
                </ol>
            </div>

            <div style="background-color: #f9fafb; border-radius: 0.375rem; padding: 0.75rem; color: #374151;">
                <strong>Asustor ADM</strong>
                <ol style="list-style-type: decimal; list-style-position: inside; margin-top: 0.25rem;">
                    <li>Installeer <strong>Portainer</strong> of <strong>Docker Manager</strong> uit App Central.</li>
                    <li>Ga naar <strong>Services</strong>, schakel SSH in.</li>
                    <li>SSH: <code>ssh root@&lt;asustor-ip&gt;</code></li>
                    <li>Plak het Optie B commando.</li>
                </ol>
            </div>

            <div style="background-color: #f9fafb; border-radius: 0.375rem; padding: 0.75rem; color: #374151;">
                <strong>Generieke Linux-server (Ubuntu, Debian, etc.)</strong>
                <ol style="list-style-type: decimal; list-style-position: inside; margin-top: 0.25rem;">
                    <li>Installeer Docker als je dat nog niet hebt: <code>curl -fsSL https://get.docker.com | sudo bash</code></li>
                    <li>SSH naar de server en plak het Optie B commando.</li>
                </ol>
            </div>

            <div style="background-color: #f9fafb; border-radius: 0.375rem; padding: 0.75rem; color: #374151;">
                <strong>Geen idee wat SSH is?</strong>
                <p style="margin-top: 0.25rem;">
                    SSH is een manier om vanaf je laptop op je NAS in te loggen om commando's uit te voeren. Op Mac/Linux: open de Terminal-app. Op Windows 10+: open <strong>PowerShell</strong> (vroeger: download <a href="https://www.putty.org/" target="_blank" style="text-decoration: underline;">PuTTY</a>).
                    Typ daar dan het ssh commando dat hierboven bij jouw NAS staat. Als alles werkt zie je <code>$</code> of <code>#</code> en kun je het optie B commando plakken.
                </p>
            </div>
        </div>

        <p style="margin-top: 1rem; color: #4b5563;">
            <em>USB-printer aan een NAS?</em> Sluit de printer aan via USB op de NAS v&oacute;&oacute;rdat je het script draait. Het script mount automatisch <code>/dev/bus/usb</code> in de container (alleen als het beschikbaar is op de host).
        </p>
    </div>

    <div>
        <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">Netwerkprinters (WiFi / Ethernet)</h3>
        <p style="margin-bottom: 0.5rem; color: #4b5563;">
            Voor printers die via netwerk bereikbaar zijn (HP, Brother, Zebra etc. met WiFi of LAN):
        </p>
        <ol style="list-style-type: decimal; list-style-position: inside; color: #4b5563;">
            <li>Zoek het IP-adres van de printer (op het scherm van de printer of via je router).</li>
            <li>Bepaal het protocol:
                <ul style="list-style-type: disc; list-style-position: inside; margin-left: 1.5rem; margin-top: 0.25rem;">
                    <li><code>socket://&lt;ip&gt;:9100</code> voor HP, Brother en de meeste JetDirect-printers (port 9100)</li>
                    <li><code>ipp://&lt;ip&gt;:631/ipp/print</code> voor moderne IPP-printers (AirPrint)</li>
                    <li><code>lpd://&lt;ip&gt;/PASSTHRU</code> voor oude LPD/LPR-printers</li>
                </ul>
            </li>
            <li>Voeg toe na pairing (kies een methode):
                <ul style="list-style-type: disc; list-style-position: inside; margin-left: 1.5rem; margin-top: 0.25rem;">
                    <li><strong>Eenvoudig (CUPS web UI):</strong> open <code>http://&lt;ip-van-host&gt;:631</code> in je browser. Klik op Administration &rarr; Add Printer.</li>
                    <li><strong>Via SSH:</strong> <code>sudo lpadmin -p naam -E -v socket://&lt;ip&gt;:9100 -m everywhere</code></li>
                    <li><strong>Via Docker compose:</strong> bewerk <code>/opt/dashedcms-printer/docker-compose.yml</code>, zet de <code>NETWORK_PRINTERS</code> env var (formaat: <code>naam=uri,naam2=uri2</code>), draai <code>docker compose up -d</code> opnieuw.</li>
                </ul>
            </li>
        </ol>
        <p style="margin-top: 0.5rem; color: #4b5563;">
            De daemon meldt nieuwe CUPS-printers automatisch terug aan dit CMS (via <code>POST /api/print/sync-printers</code>), dus na een paar minuten verschijnen ze in de dropdown op de Printer-pagina.
        </p>
    </div>

    <div style="background-color: #f3f4f6; border-radius: 0.5rem; padding: 1rem; color: #374151;">
        <strong>Verificatie en troubleshooting</strong>
        <ul style="list-style-type: disc; list-style-position: inside; margin-top: 0.25rem;">
            <li>Binnen 30 seconden na het draaien van het script moet de printer-status in het CMS naar "online" springen.</li>
            <li>Native logs op de Pi: <code>sudo journalctl -u dashedcms-printer -f</code></li>
            <li>Docker logs op de NAS: <code>sudo docker logs -f dashedcms-printer</code></li>
            <li>CUPS web UI om printers te beheren: <code>http://&lt;host-ip&gt;:631</code></li>
            <li>"401 Unauthorized" in logs: token verlopen of opnieuw gegenereerd. Klik "Opnieuw pairen" op de Printer-pagina en draai het nieuwe oneliner-commando.</li>
        </ul>
    </div>
</div>
