{!! '#!/bin/bash' !!}
{!! '# DashedCMS print queue installer voor printer "' . $printerName . '"' !!}
{!! '# Gegenereerd: ' . now()->toIso8601String() !!}
set -euo pipefail

API_URL="{{ $apiUrl }}"
TOKEN="{{ $token }}"
CUPS_NAME="{{ $cupsName }}"
PRINTER_LABEL="{{ $printerName }}"

CONFIG_DIR="/opt/dashedcms-printer"
CONFIG_FILE="$CONFIG_DIR/config.yaml"
DAEMON_FILE="$CONFIG_DIR/print_daemon.py"
SERVICE_FILE="/etc/systemd/system/dashedcms-printer.service"

echo "==> DashedCMS print queue installer"
echo "    Printer:   $PRINTER_LABEL"
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
    echo "    Zorg dat 'lpstat -p' precies '$CUPS_NAME' laat zien. Eventueel registreren met:"
    USB_URI=$(lpinfo -v 2>/dev/null | grep '^direct usb://' | head -1 | awk '{print $2}' || true)
    if [ -n "$USB_URI" ]; then
        echo "      sudo apt install -y cups && sudo lpadmin -p $CUPS_NAME -E -v '$USB_URI' -m everywhere"
    else
        echo "      sudo apt install -y cups && sudo lpadmin -p $CUPS_NAME -E -v socket://<printer-ip>:9100 -m everywhere"
    fi
    echo ""
    echo "    De daemon wordt nu wel geinstalleerd, maar print-jobs falen tot '$CUPS_NAME' bestaat."
    echo ""
fi

mkdir -p "$CONFIG_DIR"

if [ ! -f "$DAEMON_FILE" ]; then
    echo "==> Daemon downloaden..."
    curl -fsSL "$API_URL/vendor/dashed-ecommerce-core/pi/print_daemon.py" -o "$DAEMON_FILE"
else
    echo "==> Daemon bestaat al, opnieuw downloaden om laatste versie te krijgen..."
    curl -fsSL "$API_URL/vendor/dashed-ecommerce-core/pi/print_daemon.py" -o "$DAEMON_FILE"
fi

if [ ! -f "$SERVICE_FILE" ]; then
    echo "==> Systemd unit downloaden..."
    curl -fsSL "$API_URL/vendor/dashed-ecommerce-core/pi/dashedcms-printer.service" -o "$SERVICE_FILE"
fi

if [ -f "$CONFIG_FILE" ]; then
    echo "==> Bestaande config gevonden; printer '$CUPS_NAME' toevoegen of vervangen..."
    MODE="merge"
else
    echo "==> Nieuwe config schrijven..."
    MODE="create"
fi

API_URL_ENV="$API_URL" TOKEN_ENV="$TOKEN" CUPS_NAME_ENV="$CUPS_NAME" CONFIG_FILE_ENV="$CONFIG_FILE" MODE_ENV="$MODE" python3 <<'PYTHON_EOF'
import os
import yaml
from pathlib import Path

config_path = Path(os.environ["CONFIG_FILE_ENV"])
api_url = os.environ["API_URL_ENV"]
token = os.environ["TOKEN_ENV"]
cups_name = os.environ["CUPS_NAME_ENV"]
mode = os.environ["MODE_ENV"]

if mode == "merge" and config_path.exists():
    with config_path.open("r", encoding="utf-8") as f:
        cfg = yaml.safe_load(f) or {}
else:
    cfg = {}

cfg.setdefault("api_url", api_url)
cfg.setdefault("poll_interval_seconds", 5)
cfg.setdefault("log_level", "INFO")

# Old single-printer format migratie
if "printers" not in cfg:
    cfg["printers"] = []
    if cfg.get("token") and cfg.get("cups_printer"):
        cfg["printers"].append({"token": cfg.pop("token"), "cups_printer": cfg.pop("cups_printer")})
    else:
        cfg.pop("token", None)
        cfg.pop("cups_printer", None)

# Verwijder bestaande entry met dezelfde cups_name (token-rotatie)
cfg["printers"] = [p for p in cfg["printers"] if p.get("cups_printer") != cups_name]
cfg["printers"].append({"token": token, "cups_printer": cups_name})

# api_url updaten naar de meest recente versie
cfg["api_url"] = api_url

with config_path.open("w", encoding="utf-8") as f:
    yaml.safe_dump(cfg, f, default_flow_style=False, sort_keys=False)

print(f"    Config nu met {len(cfg['printers'])} printer(s):")
for p in cfg["printers"]:
    print(f"      - {p['cups_printer']}")
PYTHON_EOF

chown "$PI_USER:$PI_USER" "$CONFIG_FILE"
chmod 600 "$CONFIG_FILE"

touch /var/log/dashedcms-printer.log
chown "$PI_USER:$PI_USER" /var/log/dashedcms-printer.log

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
echo "    Live logs:   sudo journalctl -u dashedcms-printer -f"
echo "    Restart:     sudo systemctl restart dashedcms-printer"
echo "    Config:      sudo cat /opt/dashedcms-printer/config.yaml"
echo ""
echo "    Wil je nog een tweede printer toevoegen? Maak hem aan in het CMS,"
echo "    klik 'Genereer token' en draai het nieuwe install-commando."
echo "    Dit script merget hem dan bij de bestaande config zonder de andere te raken."
echo ""
