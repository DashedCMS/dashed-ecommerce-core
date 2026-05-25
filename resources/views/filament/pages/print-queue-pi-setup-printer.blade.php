@php
    /** @var \Dashed\DashedEcommerceCore\Models\Printer|null $printer */
    $apiUrl = rtrim(url('/'), '/');
    $token = $printer?->plain_token;
    $cupsName = $printer ? \Illuminate\Support\Str::of($printer->name)->slug('_')->lower()->toString() : 'mijn_printer';
    $printerLabel = $printer?->name ?? 'deze printer';

    $configYaml = "api_url: {$apiUrl}\n"
        . "token: \"" . ($token ?: '<klik eerst op "Genereer token" bovenaan deze pagina>') . "\"\n"
        . "cups_printer: {$cupsName}\n"
        . "poll_interval_seconds: 5\n"
        . "log_level: INFO\n";
@endphp

<div style="font-size: 0.875rem; line-height: 1.5; display: flex; flex-direction: column; gap: 1.5rem;">

    @if (! $token)
        <div style="background-color: #fef3c7; border-left: 4px solid #d97706; border-radius: 0.5rem; padding: 1rem; color: #78350f;">
            <strong>Stap 0:</strong> Klik eerst op de oranje "Genereer token" knop bovenaan deze pagina. Daarna kun je deze handleiding gebruiken met je echte token.
        </div>
    @else
        <div style="background-color: #d1fae5; border-left: 4px solid #059669; border-radius: 0.5rem; padding: 1rem; color: #064e3b;">
            <strong>Klaar om te installeren.</strong> Alle commando's hieronder bevatten al je token, api_url en printer-naam. Kopieer de blokken één voor één en plak ze in een SSH-sessie naar je Raspberry Pi.
        </div>
    @endif

    <div>
        <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">1. Log in op je Raspberry Pi</h3>
        <p style="margin-bottom: 0.5rem; color: #4b5563;">Vanaf je computer, open een terminal en SSH naar de Pi (vervang het IP-adres door dat van jouw Pi):</p>
        <pre style="background-color: #111827; color: #f3f4f6; border-radius: 0.375rem; padding: 0.75rem; overflow-x: auto; font-family: ui-monospace, monospace; font-size: 0.8125rem; line-height: 1.4;"><code>ssh pi@&lt;ip-van-je-pi&gt;</code></pre>
        <p style="margin-top: 0.5rem; color: #4b5563;">Standaard gebruikersnaam op Raspberry Pi OS is <code>pi</code>, het wachtwoord is wat je tijdens het flashen hebt ingesteld.</p>
    </div>

    <div>
        <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">2. CUPS en Python installeren</h3>
        <p style="margin-bottom: 0.5rem; color: #4b5563;">Eenmalige setup. Plak dit hele blok in de SSH-sessie:</p>
        <pre style="background-color: #111827; color: #f3f4f6; border-radius: 0.375rem; padding: 0.75rem; overflow-x: auto; font-family: ui-monospace, monospace; font-size: 0.8125rem; line-height: 1.4;"><code>sudo apt update
sudo apt install -y cups python3 python3-pip python3-yaml
sudo pip3 install --break-system-packages requests pyyaml
sudo usermod -a -G lpadmin pi</code></pre>
    </div>

    <div>
        <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">3. Printer aansluiten en herkennen</h3>
        <p style="margin-bottom: 0.5rem; color: #4b5563;">Sluit de printer aan via USB. Controleer of de Pi hem ziet:</p>
        <pre style="background-color: #111827; color: #f3f4f6; border-radius: 0.375rem; padding: 0.75rem; overflow-x: auto; font-family: ui-monospace, monospace; font-size: 0.8125rem; line-height: 1.4;"><code>lpinfo -v | grep usb</code></pre>
        <p style="margin-top: 0.5rem; color: #4b5563;">Verschijnt er een regel met <code>usb://...</code>? Dan is de printer herkend. Zo niet: kabel los/aan en opnieuw proberen.</p>
    </div>

    <div>
        <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">4. Printer toevoegen aan CUPS</h3>
        <p style="margin-bottom: 0.5rem; color: #4b5563;">Registreer de printer onder de naam <code>{{ $cupsName }}</code> (afgeleid van "{{ $printerLabel }}"):</p>
        <pre style="background-color: #111827; color: #f3f4f6; border-radius: 0.375rem; padding: 0.75rem; overflow-x: auto; font-family: ui-monospace, monospace; font-size: 0.8125rem; line-height: 1.4;"><code>USB_URI=$(lpinfo -v | grep usb | head -1 | awk '{print $2}')
sudo lpadmin -p {{ $cupsName }} -E -v "$USB_URI" -m everywhere
sudo cupsenable {{ $cupsName }}
sudo cupsaccept {{ $cupsName }}</code></pre>
        <p style="margin-top: 0.5rem; color: #4b5563;">Test of er iets uitkomt:</p>
        <pre style="background-color: #111827; color: #f3f4f6; border-radius: 0.375rem; padding: 0.75rem; overflow-x: auto; font-family: ui-monospace, monospace; font-size: 0.8125rem; line-height: 1.4;"><code>echo "Hallo printer" | lp -d {{ $cupsName }}</code></pre>
    </div>

    <div>
        <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">5. Daemon-bestanden downloaden naar de Pi</h3>
        <p style="margin-bottom: 0.5rem; color: #4b5563;">Plak dit blok in de SSH-sessie. Het schrijft de daemon, het systemd-bestand en de config naar <code>/opt/dashedcms-printer</code>:</p>
        <pre style="background-color: #111827; color: #f3f4f6; border-radius: 0.375rem; padding: 0.75rem; overflow-x: auto; font-family: ui-monospace, monospace; font-size: 0.8125rem; line-height: 1.4;"><code>sudo mkdir -p /opt/dashedcms-printer
sudo curl -fL -o /opt/dashedcms-printer/print_daemon.py {{ $apiUrl }}/vendor/dashed-ecommerce-core/pi/print_daemon.py
sudo curl -fL -o /etc/systemd/system/dashedcms-printer.service {{ $apiUrl }}/vendor/dashed-ecommerce-core/pi/dashedcms-printer.service
sudo touch /var/log/dashedcms-printer.log
sudo chown pi:pi /var/log/dashedcms-printer.log</code></pre>
        <p style="margin-top: 0.5rem; color: #4b5563;">
            <em>Werkt de download niet?</em> Vraag dan een ontwikkelaar om de map
            <code>{{ base_path('packages/dashed/dashed-ecommerce-core/resources/pi') }}</code>
            handmatig naar <code>/opt/dashedcms-printer/</code> op de Pi te kopi&euml;ren (via SCP of USB-stick).
        </p>
    </div>

    <div>
        <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">6. Config bestand aanmaken</h3>
        <p style="margin-bottom: 0.5rem; color: #4b5563;">Plak het hele blok hieronder in de SSH-sessie. Dit schrijft de config met jouw token + URL al ingevuld:</p>
        <pre style="background-color: #111827; color: #f3f4f6; border-radius: 0.375rem; padding: 0.75rem; overflow-x: auto; font-family: ui-monospace, monospace; font-size: 0.8125rem; line-height: 1.4;"><code>sudo tee /opt/dashedcms-printer/config.yaml &gt; /dev/null &lt;&lt;'EOF'
{{ $configYaml }}EOF
sudo chown pi:pi /opt/dashedcms-printer/config.yaml
sudo chmod 600 /opt/dashedcms-printer/config.yaml</code></pre>
        <p style="margin-top: 0.5rem; color: #4b5563;">De config ziet er straks zo uit:</p>
        <pre style="background-color: #111827; color: #f3f4f6; border-radius: 0.375rem; padding: 0.75rem; overflow-x: auto; font-family: ui-monospace, monospace; font-size: 0.8125rem; line-height: 1.4;"><code>{{ $configYaml }}</code></pre>
    </div>

    <div>
        <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">7. Service starten en op auto-start zetten</h3>
        <pre style="background-color: #111827; color: #f3f4f6; border-radius: 0.375rem; padding: 0.75rem; overflow-x: auto; font-family: ui-monospace, monospace; font-size: 0.8125rem; line-height: 1.4;"><code>sudo systemctl daemon-reload
sudo systemctl enable dashedcms-printer
sudo systemctl start dashedcms-printer
sudo systemctl status dashedcms-printer --no-pager</code></pre>
        <p style="margin-top: 0.5rem; color: #4b5563;">Bij <strong>Active: active (running)</strong> draait de daemon. De Pi gaat nu elke 5 seconden bij het CMS langs.</p>
    </div>

    <div>
        <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">8. Verifi&euml;ren</h3>
        <p style="color: #4b5563;">
            Binnen 30 seconden zou bovenaan deze pagina, onder "Laatste ping", iets als <em>"een paar seconden geleden (online)"</em> moeten verschijnen. Klik daarna boven op de blauwe <strong>Test print</strong> knop. De Pi zou binnen 5-10 seconden iets uit moeten printen.
        </p>
    </div>

    <div style="background-color: #f3f4f6; border-radius: 0.5rem; padding: 1rem; color: #374151;">
        <strong>Loopt iets vast?</strong>
        <ul style="list-style-type: disc; list-style-position: inside; margin-top: 0.25rem;">
            <li>Print niet, geen status update: vraag SSH-gebruiker om <code>sudo journalctl -u dashedcms-printer -f</code> te draaien op de Pi en stuur je de log.</li>
            <li>"401 Unauthorized" in logs: token is opnieuw gegenereerd. Klik hierboven op "Genereer token" en herhaal stap 6 op de Pi.</li>
            <li>"lp: idle, accepting" maar geen output: het papier of de USB-kabel.</li>
            <li>De curl in stap 5 faalt (404): de daemon-bestanden zijn nog niet als publieke route gepubliceerd. Laat een ontwikkelaar ze handmatig naar de Pi kopi&euml;ren (zie noot onder stap 5).</li>
        </ul>
    </div>
</div>
