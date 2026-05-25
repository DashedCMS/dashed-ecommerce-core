{!! '#!/bin/bash' !!}
{!! '# DashedCMS print queue installer (auto-discover all CUPS printers and register in CMS)' !!}
{!! '# Gegenereerd: ' . now()->toIso8601String() !!}
set -euo pipefail

API_URL="{{ $apiUrl }}"
DISCOVER_URL="{!! $discoverUrl !!}"

CONFIG_DIR="/opt/dashedcms-printer"
CONFIG_FILE="$CONFIG_DIR/config.yaml"
DAEMON_FILE="$CONFIG_DIR/print_daemon.py"
SERVICE_FILE="/etc/systemd/system/dashedcms-printer.service"

echo "==> DashedCMS print queue auto-discover installer"
echo "    API URL:   $API_URL"
echo ""

if [ "$(id -u)" -ne 0 ]; then
    echo "Run dit script met sudo: 'curl -fsSL <url> | sudo bash'"
    exit 1
fi

PI_USER="${SUDO_USER:-pi}"
HOSTNAME_LABEL="$(hostname -s 2>/dev/null || hostname)"

echo "==> Dependencies installeren..."
apt update >/dev/null
apt install -y cups-client python3 python3-pip python3-yaml curl jq >/dev/null
pip3 install --break-system-packages requests pyyaml >/dev/null 2>&1 || pip3 install requests pyyaml >/dev/null

echo ""
echo "==> CUPS printers op deze host detecteren..."
mapfile -t CUPS_NAMES < <(lpstat -p 2>/dev/null | awk '/^printer / {print $2}')

if [ "${#CUPS_NAMES[@]}" -eq 0 ]; then
    echo "    Geen CUPS printers gevonden op deze host."
    echo "    Registreer eerst minstens 1 printer:"
    echo "      USB:     sudo lpadmin -p <naam> -E -v 'usb://...' -m everywhere"
    echo "      Netwerk: sudo lpadmin -p <naam> -E -v socket://<ip>:9100 -m everywhere"
    echo "      Of via mDNS-share van een andere host: 'sudo apt install cups-browsed && sudo systemctl enable --now cups-browsed'"
    echo "    Daarna draai dit commando opnieuw."
    exit 1
fi

echo "    ${#CUPS_NAMES[@]} CUPS printer(s) gevonden:"
for name in "${CUPS_NAMES[@]}"; do
    echo "      - $name"
done

echo ""
echo "==> Doorsturen naar CMS voor registratie..."
PAYLOAD=$(jq -n \
    --arg host "$HOSTNAME_LABEL" \
    --argjson printers "$(printf '%s\n' "${CUPS_NAMES[@]}" | jq -R . | jq -s .)" \
    '{hostname: $host, discovered_printers: $printers}')

DISCOVER_RESPONSE=$(curl -sS -X POST "$DISCOVER_URL" \
    -H 'Accept: application/json' \
    -H 'Content-Type: application/json' \
    -w '\n___HTTP___%{http_code}' \
    -d "$PAYLOAD") || {
        echo "    Kon $API_URL niet bereiken." >&2
        exit 1
    }

HTTP_CODE=$(echo "$DISCOVER_RESPONSE" | sed -n 's/^___HTTP___//p')
RESPONSE=$(echo "$DISCOVER_RESPONSE" | sed '/^___HTTP___/d')

if [ "$HTTP_CODE" != "200" ]; then
    echo "    CMS gaf HTTP $HTTP_CODE terug:" >&2
    echo "    $RESPONSE" >&2
    echo "    Mogelijk is de install-link verlopen. Genereer een nieuwe in admin." >&2
    exit 1
fi

CREATED_COUNT=$(echo "$RESPONSE" | jq '.created | length')
SKIPPED_COUNT=$(echo "$RESPONSE" | jq '.skipped | length')

echo "    $CREATED_COUNT nieuwe printer(s) aangemaakt, $SKIPPED_COUNT overgeslagen (bestonden al)."

if [ "$SKIPPED_COUNT" -gt 0 ]; then
    echo "    Skipped:"
    echo "$RESPONSE" | jq -r '.skipped[] | "      - \(.cups_name): \(.reason)"'
fi

mkdir -p "$CONFIG_DIR"

echo ""
echo "==> Daemon en systemd unit downloaden..."
curl -fsSL "$API_URL/vendor/dashed-ecommerce-core/pi/print_daemon.py" -o "$DAEMON_FILE"
[ ! -f "$SERVICE_FILE" ] && curl -fsSL "$API_URL/vendor/dashed-ecommerce-core/pi/dashedcms-printer.service" -o "$SERVICE_FILE"

echo "==> Config schrijven (multi-printer)..."
API_URL_ENV="$API_URL" CONFIG_FILE_ENV="$CONFIG_FILE" RESPONSE_ENV="$RESPONSE" python3 <<'PYTHON_EOF'
import json
import os
import yaml
from pathlib import Path

config_path = Path(os.environ["CONFIG_FILE_ENV"])
api_url = os.environ["API_URL_ENV"]
response = json.loads(os.environ["RESPONSE_ENV"])

if config_path.exists():
    cfg = yaml.safe_load(config_path.read_text()) or {}
else:
    cfg = {}

cfg.setdefault("api_url", api_url)
cfg.setdefault("poll_interval_seconds", 5)
cfg.setdefault("log_level", "INFO")

if "printers" not in cfg:
    cfg["printers"] = []
    if cfg.get("token") and cfg.get("cups_printer"):
        cfg["printers"].append({"token": cfg.pop("token"), "cups_printer": cfg.pop("cups_printer")})
    else:
        cfg.pop("token", None)
        cfg.pop("cups_printer", None)

for entry in response.get("created", []):
    cups_name = entry["cups_name"]
    cfg["printers"] = [p for p in cfg["printers"] if p.get("cups_printer") != cups_name]
    cfg["printers"].append({"token": entry["token"], "cups_printer": cups_name})

cfg["api_url"] = api_url
config_path.write_text(yaml.safe_dump(cfg, default_flow_style=False, sort_keys=False))
print(f"    Config nu met {len(cfg['printers'])} printer(s):")
for p in cfg["printers"]:
    print(f"      - {p['cups_printer']}")
PYTHON_EOF

chown "$PI_USER:$PI_USER" "$CONFIG_FILE"
chmod 600 "$CONFIG_FILE"
touch /var/log/dashedcms-printer.log
chown "$PI_USER:$PI_USER" /var/log/dashedcms-printer.log

echo ""
echo "==> Service (her)starten..."
sed -i "s|^User=.*|User=$PI_USER|" "$SERVICE_FILE"
systemctl daemon-reload
systemctl enable dashedcms-printer
systemctl restart dashedcms-printer
sleep 2

echo ""
echo "==> Klaar!"
systemctl status dashedcms-printer --no-pager --lines=5 || true

echo ""
echo "    Nu in het CMS:"
echo "      1. Open Print queue -> Printers"
echo "      2. Voor elke nieuwe printer: stel het 'Doel' in (pakbon / verzendlabel / beide)"
echo "      3. Klik 'Test print' om end-to-end te testen"
echo ""
echo "    Live logs:  sudo journalctl -u dashedcms-printer -f"
echo ""
