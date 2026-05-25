@php
    /** @var \Dashed\DashedEcommerceCore\Models\Printer|null $printer */
    $apiUrl = rtrim(url('/'), '/');
    $token = $printer?->plain_token;
    $cupsName = $printer ? \Illuminate\Support\Str::of($printer->name)->slug('_')->lower()->toString() : 'mijn_printer';
    $printerLabel = $printer?->name ?? 'deze printer';

    $installerUrl = $printer && $token
        ? \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'dashed.print-queue.installer',
            now()->addHours(2),
            ['ulid' => $printer->ulid],
        )
        : null;

    $oneLiner = $installerUrl ? 'curl -fsSL "' . $installerUrl . '" | sudo bash' : null;
@endphp

<div style="font-size: 0.875rem; line-height: 1.5; display: flex; flex-direction: column; gap: 1.5rem;">

    @if (! $token)
        <div style="background-color: #fef3c7; border-left: 4px solid #d97706; border-radius: 0.5rem; padding: 1rem; color: #78350f;">
            <strong>Eerst een token genereren.</strong> Klik op de oranje "Genereer token" knop bovenaan deze pagina. Daarna verschijnt hier het complete installatie-commando.
        </div>
    @else
        <div style="background-color: #d1fae5; border-left: 4px solid #059669; border-radius: 0.5rem; padding: 1rem; color: #064e3b;">
            <strong>Snelle installatie (aanbevolen).</strong>
            <p style="margin-top: 0.5rem; margin-bottom: 0.5rem;">
                Log in op de Raspberry Pi (via SSH met <code>ssh pi@&lt;ip-van-je-pi&gt;</code>), plak het commando hieronder, druk Enter. Het script installeert CUPS, registreert je USB-printer, downloadt de daemon, configureert hem met jouw token en start de service.
            </p>
            <div style="display: flex; gap: 0.5rem; align-items: stretch; flex-wrap: wrap; margin-top: 0.75rem;">
                <code style="background-color: #111827; color: #f3f4f6; padding: 0.75rem; border-radius: 0.375rem; font-family: ui-monospace, monospace; font-size: 0.8125rem; word-break: break-all; flex: 1; min-width: 0; line-height: 1.4;">{{ $oneLiner }}</code>
                <button type="button"
                    onclick="navigator.clipboard.writeText('{{ $oneLiner }}'); this.textContent = 'Gekopieerd'; setTimeout(() => this.textContent = 'Kopieer commando', 1500);"
                    style="background-color: #059669; color: #ffffff; border: none; padding: 0.5rem 1rem; border-radius: 0.375rem; font-size: 0.8125rem; cursor: pointer; font-weight: 500; white-space: nowrap;">Kopieer commando</button>
            </div>
            <p style="margin-top: 0.75rem; font-size: 0.75rem;">
                De link is 2 uur geldig. Werkt het niet binnen die tijd, ververs deze pagina dan voor een nieuwe link.
            </p>
        </div>
    @endif

    @if ($token)
        <details>
            <summary style="cursor: pointer; font-weight: 600; color: #4b5563;">Liever stap-voor-stap handmatig? (klap uit)</summary>
            <div style="margin-top: 1rem; display: flex; flex-direction: column; gap: 1.5rem;">

                <div>
                    <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">1. SSH naar de Pi</h3>
                    <pre style="background-color: #111827; color: #f3f4f6; border-radius: 0.375rem; padding: 0.75rem; overflow-x: auto; font-family: ui-monospace, monospace; font-size: 0.8125rem; line-height: 1.4;"><code>ssh pi@&lt;ip-van-je-pi&gt;</code></pre>
                </div>

                <div>
                    <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">2. CUPS en Python installeren</h3>
                    <pre style="background-color: #111827; color: #f3f4f6; border-radius: 0.375rem; padding: 0.75rem; overflow-x: auto; font-family: ui-monospace, monospace; font-size: 0.8125rem; line-height: 1.4;"><code>sudo apt update
sudo apt install -y cups python3 python3-pip python3-yaml
sudo pip3 install --break-system-packages requests pyyaml
sudo usermod -a -G lpadmin pi</code></pre>
                </div>

                <div>
                    <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">3. Printer registreren in CUPS (naam: <code>{{ $cupsName }}</code>)</h3>
                    <pre style="background-color: #111827; color: #f3f4f6; border-radius: 0.375rem; padding: 0.75rem; overflow-x: auto; font-family: ui-monospace, monospace; font-size: 0.8125rem; line-height: 1.4;"><code>USB_URI=$(lpinfo -v | grep usb | head -1 | awk '{print $2}')
sudo lpadmin -p {{ $cupsName }} -E -v "$USB_URI" -m everywhere
sudo cupsenable {{ $cupsName }}
sudo cupsaccept {{ $cupsName }}</code></pre>
                </div>

                <div>
                    <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">4. Daemon downloaden</h3>
                    <pre style="background-color: #111827; color: #f3f4f6; border-radius: 0.375rem; padding: 0.75rem; overflow-x: auto; font-family: ui-monospace, monospace; font-size: 0.8125rem; line-height: 1.4;"><code>sudo mkdir -p /opt/dashedcms-printer
sudo curl -fsSL {{ $apiUrl }}/vendor/dashed-ecommerce-core/pi/print_daemon.py -o /opt/dashedcms-printer/print_daemon.py
sudo curl -fsSL {{ $apiUrl }}/vendor/dashed-ecommerce-core/pi/dashedcms-printer.service -o /etc/systemd/system/dashedcms-printer.service</code></pre>
                </div>

                <div>
                    <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">5. Config schrijven (token al ingevuld)</h3>
                    <pre style="background-color: #111827; color: #f3f4f6; border-radius: 0.375rem; padding: 0.75rem; overflow-x: auto; font-family: ui-monospace, monospace; font-size: 0.8125rem; line-height: 1.4;"><code>sudo tee /opt/dashedcms-printer/config.yaml &gt; /dev/null &lt;&lt;'EOF'
api_url: {{ $apiUrl }}
token: "{{ $token }}"
cups_printer: {{ $cupsName }}
poll_interval_seconds: 5
log_level: INFO
EOF
sudo chmod 600 /opt/dashedcms-printer/config.yaml
sudo touch /var/log/dashedcms-printer.log
sudo chown pi:pi /opt/dashedcms-printer/config.yaml /var/log/dashedcms-printer.log</code></pre>
                </div>

                <div>
                    <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">6. Service starten</h3>
                    <pre style="background-color: #111827; color: #f3f4f6; border-radius: 0.375rem; padding: 0.75rem; overflow-x: auto; font-family: ui-monospace, monospace; font-size: 0.8125rem; line-height: 1.4;"><code>sudo systemctl daemon-reload
sudo systemctl enable dashedcms-printer
sudo systemctl start dashedcms-printer
sudo systemctl status dashedcms-printer --no-pager</code></pre>
                </div>
            </div>
        </details>
    @endif

    <div style="background-color: #f3f4f6; border-radius: 0.5rem; padding: 1rem; color: #374151;">
        <strong>Verificatie en troubleshooting</strong>
        <ul style="list-style-type: disc; list-style-position: inside; margin-top: 0.25rem;">
            <li>Binnen 30 seconden moet de status bovenaan deze pagina naar "online" springen.</li>
            <li>Test een echte print via de blauwe <strong>Test print</strong> knop bovenin.</li>
            <li>Live logs bekijken op de Pi: <code>sudo journalctl -u dashedcms-printer -f</code></li>
            <li>"401 Unauthorized" in logs: token is opnieuw gegenereerd. Klik bovenaan op "Genereer token" en draai de oneliner opnieuw.</li>
            <li>Geen USB printer gevonden: kabel los/aan, daarna opnieuw. <code>lpstat -p</code> op de Pi laat zien welke printers CUPS kent.</li>
        </ul>
    </div>
</div>
