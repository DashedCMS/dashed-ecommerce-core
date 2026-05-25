@php
    $packageRoot = base_path('packages/dashed/dashed-ecommerce-core/resources/pi');
    $printerCreateUrl = filled(\Dashed\DashedEcommerceCore\Filament\Resources\PrinterResource::class)
        ? \Dashed\DashedEcommerceCore\Filament\Resources\PrinterResource::getUrl('index')
        : null;
@endphp

<div class="space-y-6 text-sm">
    <div class="rounded-lg bg-amber-50 dark:bg-amber-900/30 p-4 text-amber-900 dark:text-amber-100">
        <strong>Voorbereiding:</strong>
        Maak eerst een printer aan en genereer een Sanctum token via
        @if ($printerCreateUrl)
            <a href="{{ $printerCreateUrl }}" class="underline font-medium">Admin &rarr; Print queue &rarr; Printers</a>.
        @else
            Admin &rarr; Print queue &rarr; Printers.
        @endif
        Het token wordt 1 keer getoond, kopieer hem direct.
    </div>

    <div>
        <h3 class="text-base font-semibold mb-2">1. Daemon-bestanden ophalen</h3>
        <p class="mb-2 text-gray-600 dark:text-gray-400">De daemon, systemd-unit en config-template staan in het package onder:</p>
        <pre class="rounded bg-gray-900 text-gray-100 p-3 overflow-x-auto"><code>{{ $packageRoot }}/</code></pre>
        <p class="mt-2 text-gray-600 dark:text-gray-400">Kopieer deze map naar de Pi via SCP of git clone, of publiceer hem in de root van het project:</p>
        <pre class="rounded bg-gray-900 text-gray-100 p-3 overflow-x-auto"><code>scp -r {{ $packageRoot }}/* pi@&lt;pi-host&gt;:/tmp/dashedcms-printer/</code></pre>
    </div>

    <div>
        <h3 class="text-base font-semibold mb-2">2. CUPS en Python installeren op de Pi</h3>
        <pre class="rounded bg-gray-900 text-gray-100 p-3 overflow-x-auto"><code>sudo apt update
sudo apt install -y cups python3 python3-pip python3-yaml
sudo pip3 install requests pyyaml</code></pre>
    </div>

    <div>
        <h3 class="text-base font-semibold mb-2">3. Printer toevoegen aan CUPS</h3>
        <p class="mb-2 text-gray-600 dark:text-gray-400">Sluit de USB-printer aan en registreer hem onder een naam die je in de daemon-config gaat gebruiken (bijvoorbeeld <code>pakbon_brother</code>):</p>
        <pre class="rounded bg-gray-900 text-gray-100 p-3 overflow-x-auto"><code>sudo lpadmin -p pakbon_brother -E \
    -v "$(lpinfo -v | grep usb | head -1 | awk '{print $2}')" \
    -m drv:///sample.drv/laserjet.ppd

sudo cupsenable pakbon_brother
sudo cupsaccept pakbon_brother</code></pre>
        <p class="mt-2 text-gray-600 dark:text-gray-400">Test of de printer reageert:</p>
        <pre class="rounded bg-gray-900 text-gray-100 p-3 overflow-x-auto"><code>echo "Hallo printer" | lp -d pakbon_brother</code></pre>
    </div>

    <div>
        <h3 class="text-base font-semibold mb-2">4. Daemon-bestanden installeren</h3>
        <pre class="rounded bg-gray-900 text-gray-100 p-3 overflow-x-auto"><code>sudo mkdir -p /opt/dashedcms-printer
sudo cp /tmp/dashedcms-printer/print_daemon.py /opt/dashedcms-printer/
sudo cp /tmp/dashedcms-printer/config.example.yaml /opt/dashedcms-printer/config.yaml
sudo cp /tmp/dashedcms-printer/dashedcms-printer.service /etc/systemd/system/
sudo touch /var/log/dashedcms-printer.log
sudo chown pi:pi /var/log/dashedcms-printer.log /opt/dashedcms-printer/config.yaml</code></pre>
    </div>

    <div>
        <h3 class="text-base font-semibold mb-2">5. Config invullen</h3>
        <p class="mb-2 text-gray-600 dark:text-gray-400">Vul je gegenereerde token en de CUPS printer-naam in:</p>
        <pre class="rounded bg-gray-900 text-gray-100 p-3 overflow-x-auto"><code>sudo nano /opt/dashedcms-printer/config.yaml</code></pre>
        <p class="mt-2 mb-2 text-gray-600 dark:text-gray-400">Voorbeeld:</p>
        <pre class="rounded bg-gray-900 text-gray-100 p-3 overflow-x-auto"><code>api_url: {{ rtrim(url('/'), '/') }}
token: "1|jouw-sanctum-token-hier..."
cups_printer: pakbon_brother
poll_interval_seconds: 5
log_level: INFO</code></pre>
    </div>

    <div>
        <h3 class="text-base font-semibold mb-2">6. Service starten</h3>
        <pre class="rounded bg-gray-900 text-gray-100 p-3 overflow-x-auto"><code>sudo systemctl daemon-reload
sudo systemctl enable dashedcms-printer
sudo systemctl start dashedcms-printer
sudo systemctl status dashedcms-printer</code></pre>
    </div>

    <div>
        <h3 class="text-base font-semibold mb-2">7. Live logs bekijken</h3>
        <pre class="rounded bg-gray-900 text-gray-100 p-3 overflow-x-auto"><code>sudo journalctl -u dashedcms-printer -f
# of
sudo tail -f /var/log/dashedcms-printer.log</code></pre>
    </div>

    <div>
        <h3 class="text-base font-semibold mb-2">8. Verifieer in admin</h3>
        <p class="text-gray-600 dark:text-gray-400">
            Binnen 30 seconden zou de printer status op
            @if ($printerCreateUrl)
                <a href="{{ $printerCreateUrl }}" class="underline font-medium">Printers</a>
            @else
                de Printers-pagina
            @endif
            naar <strong>online</strong> moeten springen (groene dot). Maak een test print via de Test print-knop op de printer-pagina om end-to-end te valideren.
        </p>
    </div>

    <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4 text-gray-700 dark:text-gray-300">
        <strong>Troubleshooting:</strong>
        <ul class="list-disc list-inside mt-1 space-y-1">
            <li>Status blijft offline: check token in <code>config.yaml</code> en kijk in <code>journalctl -u dashedcms-printer -f</code>.</li>
            <li><code>lp</code> faalt: <code>lpstat -p</code> om de printer-naam te controleren; in CUPS web-UI op <code>http://&lt;pi-host&gt;:631</code> kun je een testprint sturen.</li>
            <li>Jobs blijven in <em>claimed</em>: daemon crasht na claim. Logs vertellen waarom (meestal CUPS-probleem of network timeout).</li>
            <li>API geeft 401: token is ingetrokken via Genereer-token-knop; maak een nieuwe en update <code>config.yaml</code>.</li>
        </ul>
    </div>
</div>
