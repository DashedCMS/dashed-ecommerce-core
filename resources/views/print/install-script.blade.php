{!! '#!/bin/bash' !!}
{!! '# DashedCMS print queue installer for printer "' . $printerName . '"' !!}
{!! '# Generated: ' . now()->toIso8601String() !!}
set -euo pipefail

echo "==> DashedCMS print queue installer"
echo "    Printer: {{ $printerName }}"
echo "    API URL: {{ $apiUrl }}"
echo "    CUPS name: {{ $cupsName }}"
echo ""

if [ "$(id -u)" -ne 0 ]; then
    echo "Run this script with sudo: 'curl -fsSL <url> | sudo bash'"
    exit 1
fi

PI_USER="${SUDO_USER:-pi}"

echo "==> Installing dependencies (apt + pip)..."
apt update
apt install -y cups python3 python3-pip python3-yaml curl
pip3 install --break-system-packages requests pyyaml >/dev/null 2>&1 || pip3 install requests pyyaml
usermod -a -G lpadmin "$PI_USER" || true

echo ""
echo "==> Detecting USB printer..."
USB_URI=$(lpinfo -v 2>/dev/null | grep usb | head -1 | awk '{print $2}' || true)
if [ -z "$USB_URI" ]; then
    echo "ERROR: No USB printer detected. Plug in your printer and re-run the installer."
    exit 1
fi
echo "    Found: $USB_URI"

echo ""
echo "==> Registering printer with CUPS as '{{ $cupsName }}'..."
lpadmin -p "{{ $cupsName }}" -E -v "$USB_URI" -m everywhere || lpadmin -p "{{ $cupsName }}" -E -v "$USB_URI" -m drv:///sample.drv/laserjet.ppd
cupsenable "{{ $cupsName }}"
cupsaccept "{{ $cupsName }}"

echo ""
echo "==> Downloading daemon..."
mkdir -p /opt/dashedcms-printer
curl -fsSL "{{ $apiUrl }}/vendor/dashed-ecommerce-core/pi/print_daemon.py" -o /opt/dashedcms-printer/print_daemon.py
curl -fsSL "{{ $apiUrl }}/vendor/dashed-ecommerce-core/pi/dashedcms-printer.service" -o /etc/systemd/system/dashedcms-printer.service

echo ""
echo "==> Writing config..."
cat > /opt/dashedcms-printer/config.yaml <<'CONFIG_EOF'
api_url: {{ $apiUrl }}
token: "{{ $token }}"
cups_printer: {{ $cupsName }}
poll_interval_seconds: 5
log_level: INFO
CONFIG_EOF
chown "$PI_USER:$PI_USER" /opt/dashedcms-printer/config.yaml
chmod 600 /opt/dashedcms-printer/config.yaml

touch /var/log/dashedcms-printer.log
chown "$PI_USER:$PI_USER" /var/log/dashedcms-printer.log

echo ""
echo "==> Starting service..."
sed -i "s|^User=.*|User=$PI_USER|" /etc/systemd/system/dashedcms-printer.service
systemctl daemon-reload
systemctl enable dashedcms-printer
systemctl restart dashedcms-printer
sleep 2

echo ""
echo "==> Done!"
echo ""
systemctl status dashedcms-printer --no-pager --lines=5 || true

echo ""
echo "    Live logs:    sudo journalctl -u dashedcms-printer -f"
echo "    Test print:   ga in het CMS naar de Printers-pagina en klik 'Test print'"
echo ""
