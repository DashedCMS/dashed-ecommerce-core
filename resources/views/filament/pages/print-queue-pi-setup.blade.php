@php
    $packageRoot = base_path('packages/dashed/dashed-ecommerce-core/resources/pi');
    $printerCreateUrl = filled(\Dashed\DashedEcommerceCore\Filament\Resources\PrinterResource::class)
        ? \Dashed\DashedEcommerceCore\Filament\Resources\PrinterResource::getUrl('index')
        : null;
@endphp

<div style="font-size: 0.875rem; line-height: 1.5; display: flex; flex-direction: column; gap: 1.5rem;">
    <div style="background-color: #fef3c7; border-left: 4px solid #d97706; border-radius: 0.5rem; padding: 1rem; color: #78350f;">
        <strong>Voorbereiding:</strong>
        Maak eerst een printer aan en genereer een Sanctum token via
        @if ($printerCreateUrl)
            <a href="{{ $printerCreateUrl }}" style="text-decoration: underline; font-weight: 600; color: inherit;">Admin &rarr; Print queue &rarr; Printers</a>.
        @else
            Admin &rarr; Print queue &rarr; Printers.
        @endif
        Het token wordt 1 keer getoond, kopieer hem direct.
    </div>

    <div style="margin-top: 1.5rem;">
        <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">1. Daemon-bestanden ophalen</h3>
        <p style="margin-bottom: 0.5rem; color: #4b5563;">De daemon, systemd-unit en config-template staan in het package onder:</p>
        <pre style="background-color: #111827; color: #f3f4f6; border-radius: 0.375rem; padding: 0.75rem; overflow-x: auto; font-family: ui-monospace, monospace; font-size: 0.8125rem; line-height: 1.4;"><code>{{ $packageRoot }}/</code></pre>
        <p style="margin-top: 0.5rem; color: #4b5563;">Kopieer deze map naar de Pi via SCP of git clone, of publiceer hem in de root van het project:</p>
        <pre style="background-color: #111827; color: #f3f4f6; border-radius: 0.375rem; padding: 0.75rem; overflow-x: auto; font-family: ui-monospace, monospace; font-size: 0.8125rem; line-height: 1.4;"><code>scp -r {{ $packageRoot }}/* pi@&lt;pi-host&gt;:/tmp/dashedcms-printer/</code></pre>
    </div>

    <div style="margin-top: 1.5rem;">
        <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">2. CUPS en Python installeren op de Pi</h3>
        <pre style="background-color: #111827; color: #f3f4f6; border-radius: 0.375rem; padding: 0.75rem; overflow-x: auto; font-family: ui-monospace, monospace; font-size: 0.8125rem; line-height: 1.4;"><code>sudo apt update
sudo apt install -y cups python3 python3-pip python3-yaml
sudo pip3 install requests pyyaml</code></pre>
    </div>

    <div style="margin-top: 1.5rem;">
        <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">3. Printer toevoegen aan CUPS</h3>
        <p style="margin-bottom: 0.5rem; color: #4b5563;">Sluit de USB-printer aan en registreer hem onder een naam die je in de daemon-config gaat gebruiken (bijvoorbeeld <code>pakbon_brother</code>):</p>
        <pre style="background-color: #111827; color: #f3f4f6; border-radius: 0.375rem; padding: 0.75rem; overflow-x: auto; font-family: ui-monospace, monospace; font-size: 0.8125rem; line-height: 1.4;"><code>sudo lpadmin -p pakbon_brother -E \
    -v "$(lpinfo -v | grep usb | head -1 | awk '{print $2}')" \
    -m drv:///sample.drv/laserjet.ppd

sudo cupsenable pakbon_brother
sudo cupsaccept pakbon_brother</code></pre>
        <p style="margin-top: 0.5rem; color: #4b5563;">Test of de printer reageert:</p>
        <pre style="background-color: #111827; color: #f3f4f6; border-radius: 0.375rem; padding: 0.75rem; overflow-x: auto; font-family: ui-monospace, monospace; font-size: 0.8125rem; line-height: 1.4;"><code>echo "Hallo printer" | lp -d pakbon_brother</code></pre>
    </div>

    <div style="margin-top: 1.5rem;">
        <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">4. Daemon-bestanden installeren</h3>
        <pre style="background-color: #111827; color: #f3f4f6; border-radius: 0.375rem; padding: 0.75rem; overflow-x: auto; font-family: ui-monospace, monospace; font-size: 0.8125rem; line-height: 1.4;"><code>sudo mkdir -p /opt/dashedcms-printer
sudo cp /tmp/dashedcms-printer/print_daemon.py /opt/dashedcms-printer/
sudo cp /tmp/dashedcms-printer/config.example.yaml /opt/dashedcms-printer/config.yaml
sudo cp /tmp/dashedcms-printer/dashedcms-printer.service /etc/systemd/system/
sudo touch /var/log/dashedcms-printer.log
sudo chown pi:pi /var/log/dashedcms-printer.log /opt/dashedcms-printer/config.yaml</code></pre>
    </div>

    <div style="margin-top: 1.5rem;">
        <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">5. Config invullen</h3>
        <p style="margin-bottom: 0.5rem; color: #4b5563;">Vul je gegenereerde token en de CUPS printer-naam in:</p>
        <pre style="background-color: #111827; color: #f3f4f6; border-radius: 0.375rem; padding: 0.75rem; overflow-x: auto; font-family: ui-monospace, monospace; font-size: 0.8125rem; line-height: 1.4;"><code>sudo nano /opt/dashedcms-printer/config.yaml</code></pre>
        <p style="margin-top: 0.5rem; margin-bottom: 0.5rem; color: #4b5563;">Voorbeeld:</p>
        <pre style="background-color: #111827; color: #f3f4f6; border-radius: 0.375rem; padding: 0.75rem; overflow-x: auto; font-family: ui-monospace, monospace; font-size: 0.8125rem; line-height: 1.4;"><code>api_url: {{ rtrim(url('/'), '/') }}
token: "1|jouw-sanctum-token-hier..."
cups_printer: pakbon_brother
poll_interval_seconds: 5
log_level: INFO</code></pre>
    </div>

    <div style="margin-top: 1.5rem;">
        <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">6. Service starten</h3>
        <pre style="background-color: #111827; color: #f3f4f6; border-radius: 0.375rem; padding: 0.75rem; overflow-x: auto; font-family: ui-monospace, monospace; font-size: 0.8125rem; line-height: 1.4;"><code>sudo systemctl daemon-reload
sudo systemctl enable dashedcms-printer
sudo systemctl start dashedcms-printer
sudo systemctl status dashedcms-printer</code></pre>
    </div>

    <div style="margin-top: 1.5rem;">
        <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">7. Live logs bekijken</h3>
        <pre style="background-color: #111827; color: #f3f4f6; border-radius: 0.375rem; padding: 0.75rem; overflow-x: auto; font-family: ui-monospace, monospace; font-size: 0.8125rem; line-height: 1.4;"><code>sudo journalctl -u dashedcms-printer -f
# of
sudo tail -f /var/log/dashedcms-printer.log</code></pre>
    </div>

    <div style="margin-top: 1.5rem;">
        <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">8. Verifieer in admin</h3>
        <p style="color: #4b5563;">
            Binnen 30 seconden zou de printer status op
            @if ($printerCreateUrl)
                <a href="{{ $printerCreateUrl }}" style="text-decoration: underline; font-weight: 600;">Printers</a>
            @else
                de Printers-pagina
            @endif
            naar <strong>online</strong> moeten springen (groene dot). Maak een test print via de Test print-knop op de printer-pagina om end-to-end te valideren.
        </p>
    </div>

    <div style="background-color: #f3f4f6; border-radius: 0.5rem; padding: 1rem; color: #374151;">
        <strong>Troubleshooting:</strong>
        <ul style="list-style-type: disc; list-style-position: inside; margin-top: 0.25rem;">
            <li>Status blijft offline: check token in <code>config.yaml</code> en kijk in <code>journalctl -u dashedcms-printer -f</code>.</li>
            <li><code>lp</code> faalt: <code>lpstat -p</code> om de printer-naam te controleren; in CUPS web-UI op <code>http://&lt;pi-host&gt;:631</code> kun je een testprint sturen.</li>
            <li>Jobs blijven in <em>claimed</em>: daemon crasht na claim. Logs vertellen waarom (meestal CUPS-probleem of network timeout).</li>
            <li>API geeft 401: token is ingetrokken via Genereer-token-knop; maak een nieuwe en update <code>config.yaml</code>.</li>
        </ul>
    </div>
</div>
