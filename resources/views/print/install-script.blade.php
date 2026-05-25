{!! '#!/bin/bash' !!}
{!! '# DashedCMS print queue installer (pairing flow)' !!}
{!! '# Generated: ' . now()->toIso8601String() !!}
set -euo pipefail

API_URL="{{ $apiUrl }}"
PAIRING_CODE="{{ $pairingCode }}"

echo "==> DashedCMS print queue installer"
echo "    API URL:      $API_URL"
echo "    Pairing code: $PAIRING_CODE"
echo ""

if [ "$(id -u)" -ne 0 ]; then
    echo "Run dit script met sudo: 'curl -fsSL <url> | sudo bash'"
    exit 1
fi

PI_USER="${SUDO_USER:-pi}"
HOSTNAME_NAME="$(hostname -s 2>/dev/null || hostname)"

echo "==> Dependencies installeren (apt + pip)..."
apt update >/dev/null
apt install -y cups python3 python3-pip python3-yaml curl jq >/dev/null
pip3 install --break-system-packages requests pyyaml >/dev/null 2>&1 || pip3 install requests pyyaml >/dev/null
usermod -a -G lpadmin "$PI_USER" || true

echo ""
echo "==> USB-printers detecteren..."
USB_LINES=$(lpinfo -v 2>/dev/null | grep usb || true)
if [ -z "$USB_LINES" ]; then
    echo "    Geen USB-printers gevonden. Eventuele netwerkprinters voeg je later toe via SSH + 'sudo lpadmin'."
fi

declare -a CUPS_NAMES=()
declare -a DEVICE_URIS=()

if [ -n "$USB_LINES" ]; then
    INDEX=1
    while IFS= read -r LINE; do
        URI=$(echo "$LINE" | awk '{print $2}')
        if [ -z "$URI" ]; then continue; fi
        CUPS_NAME="dashed_usb_${INDEX}"
        echo "    [$INDEX] $URI -> CUPS naam: $CUPS_NAME"
        lpadmin -p "$CUPS_NAME" -E -v "$URI" -m everywhere 2>/dev/null \
            || lpadmin -p "$CUPS_NAME" -E -v "$URI" -m drv:///sample.drv/laserjet.ppd
        cupsenable "$CUPS_NAME" >/dev/null 2>&1 || true
        cupsaccept "$CUPS_NAME" >/dev/null 2>&1 || true
        CUPS_NAMES+=("$CUPS_NAME")
        DEVICE_URIS+=("$URI")
        INDEX=$((INDEX+1))
    done <<< "$USB_LINES"
fi

echo ""
echo "==> Daemon downloaden..."
mkdir -p /opt/dashedcms-printer
curl -fsSL "$API_URL/vendor/dashed-ecommerce-core/pi/print_daemon.py" -o /opt/dashedcms-printer/print_daemon.py
curl -fsSL "$API_URL/vendor/dashed-ecommerce-core/pi/dashedcms-printer.service" -o /etc/systemd/system/dashedcms-printer.service

echo ""
echo "==> Pairing met CMS..."

if [ "${#CUPS_NAMES[@]}" -gt 0 ]; then
    NAMES_JSON=$(printf '%s\n' "${CUPS_NAMES[@]}" | jq -R . | jq -s .)
    URIS_JSON=$(printf '%s\n' "${DEVICE_URIS[@]}" | jq -R . | jq -s .)
else
    NAMES_JSON='[]'
    URIS_JSON='[]'
fi

PRINTERS_JSON=$(jq -n \
    --argjson names "$NAMES_JSON" \
    --argjson uris  "$URIS_JSON" \
    '[range(0; ($names|length)) | {cups_name: $names[.], device_uri: $uris[.]}]')

PAYLOAD=$(jq -n \
    --arg code "$PAIRING_CODE" \
    --arg host "$HOSTNAME_NAME" \
    --argjson printers "$PRINTERS_JSON" \
    '{pairing_code: $code, hostname: $host, discovered_printers: $printers}')

RESPONSE=$(curl -fsS -X POST "$API_URL/api/print/pair" \
    -H "Accept: application/json" \
    -H "Content-Type: application/json" \
    -d "$PAYLOAD") || {
        echo "Pairing met CMS faalde. Controleer dat de pairing code nog geldig is."
        exit 1
    }

TOKEN=$(echo "$RESPONSE" | jq -r .token)
CUPS_NAME=$(echo "$RESPONSE" | jq -r '.cups_name // ""')

if [ -z "$TOKEN" ] || [ "$TOKEN" = "null" ]; then
    echo "Geen token ontvangen van CMS. Response:"
    echo "$RESPONSE"
    exit 1
fi

if [ -z "$CUPS_NAME" ] || [ "$CUPS_NAME" = "null" ]; then
    CUPS_NAME="${CUPS_NAMES[0]:-}"
fi

if [ -z "$CUPS_NAME" ]; then
    echo "Geen actieve CUPS-printer beschikbaar. Voeg er later 1 toe in het admin paneel."
    CUPS_NAME="placeholder"
fi

echo "    Pairing succesvol. Actieve CUPS-printer: $CUPS_NAME"

echo ""
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

echo ""
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
echo "    Live logs:  sudo journalctl -u dashedcms-printer -f"
echo "    Test print: open het CMS, ga naar Printers, klik 'Test print'"
echo ""
echo "    Heb je meerdere fysieke printers? Wijs ze toe in het CMS via de Printer-pagina:"
echo "    je kunt daar uit alle ontdekte CUPS-printers kiezen welke deze Pi gebruikt."
echo ""
