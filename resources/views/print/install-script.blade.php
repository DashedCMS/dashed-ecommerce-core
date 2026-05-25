{!! '#!/bin/bash' !!}
{!! '# DashedCMS print queue installer voor printer "' . $printerName . '"' !!}
{!! '# Gegenereerd: ' . now()->toIso8601String() !!}
set -euo pipefail

API_URL="{{ $apiUrl }}"
TOKEN="{{ $token }}"
CUPS_NAME="{{ $cupsName }}"

echo "==> DashedCMS print queue installer"
echo "    Printer:   {{ $printerName }}"
echo "    API URL:   $API_URL"
echo "    CUPS naam: $CUPS_NAME"
echo ""

if [ "$(id -u)" -ne 0 ]; then
    echo "Run dit script met sudo: 'curl -fsSL <url> | sudo bash'"
    exit 1
fi

PI_USER="${SUDO_USER:-pi}"

echo "==> Dependencies installeren..."
apt update >/dev/null
apt install -y cups-client python3 python3-pip python3-yaml curl >/dev/null
pip3 install --break-system-packages requests pyyaml >/dev/null 2>&1 || pip3 install requests pyyaml >/dev/null

echo ""
echo "==> Controleren of CUPS-printer '$CUPS_NAME' bestaat op deze host..."
if ! lpstat -p "$CUPS_NAME" >/dev/null 2>&1; then
    echo ""
    echo "    LET OP: er is op deze host nog geen CUPS-printer met de naam '$CUPS_NAME'."
    echo "    Beschikbare CUPS-printers op deze host:"
    lpstat -p 2>/dev/null | sed 's/^/      /' || echo "      (geen geconfigureerd)"
    echo ""
    echo "    Voeg een USB-printer toe met:"
    USB_URI=$(lpinfo -v 2>/dev/null | grep '^direct usb://' | head -1 | awk '{print $2}' || true)
    if [ -n "$USB_URI" ]; then
        echo "      sudo apt install -y cups && sudo lpadmin -p $CUPS_NAME -E -v '$USB_URI' -m everywhere"
    else
        echo "      sudo apt install -y cups && sudo lpadmin -p $CUPS_NAME -E -v <device-uri> -m everywhere"
        echo "      (waar <device-uri> bv 'usb://Brother/HL-...' of 'socket://192.168.x.x:9100' is)"
    fi
    echo ""
    echo "    Of een netwerkprinter:"
    echo "      sudo apt install -y cups && sudo lpadmin -p $CUPS_NAME -E -v socket://<printer-ip>:9100 -m everywhere"
    echo ""
    echo "    De daemon wordt nu wel geinstalleerd, maar print-jobs zullen falen tot '$CUPS_NAME' bestaat."
    echo ""
fi

echo "==> Daemon downloaden..."
mkdir -p /opt/dashedcms-printer
curl -fsSL "$API_URL/vendor/dashed-ecommerce-core/pi/print_daemon.py" -o /opt/dashedcms-printer/print_daemon.py
curl -fsSL "$API_URL/vendor/dashed-ecommerce-core/pi/dashedcms-printer.service" -o /etc/systemd/system/dashedcms-printer.service

echo "==> Config schrijven..."
cat > /opt/dashedcms-printer/config.yaml <<CONFIG_EOF
api_url: $API_URL
token: "$TOKEN"
cups_printer: $CUPS_NAME
poll_interval_seconds: 5
log_level: INFO
CONFIG_EOF
chown "$PI_USER:$PI_USER" /opt/dashedcms-printer/config.yaml
chmod 600 /opt/dashedcms-printer/config.yaml

touch /var/log/dashedcms-printer.log
chown "$PI_USER:$PI_USER" /var/log/dashedcms-printer.log

echo "==> Service starten..."
sed -i "s|^User=.*|User=$PI_USER|" /etc/systemd/system/dashedcms-printer.service
systemctl daemon-reload
systemctl enable dashedcms-printer
systemctl restart dashedcms-printer
sleep 2

echo ""
echo "==> Klaar!"
systemctl status dashedcms-printer --no-pager --lines=5 || true

echo ""
echo "    Live logs:   sudo journalctl -u dashedcms-printer -f"
echo "    Restart:     sudo systemctl restart dashedcms-printer"
echo "    Test print:  ga in het CMS naar de Printers-pagina en klik 'Test print'"
echo ""
