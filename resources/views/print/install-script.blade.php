{!! '#!/bin/bash' !!}
{!! '# DashedCMS print queue installer voor printer "' . $printerName . '"' !!}
{!! '# Gegenereerd: ' . now()->toIso8601String() !!}
set -euo pipefail

API_URL="{{ $apiUrl }}"
TOKEN="{{ $token }}"
CUPS_NAME="{{ $cupsName }}"
PRINTER_LABEL="{{ $printerName }}"

OS="$(uname -s)"
CONFIG_DIR="/opt/dashedcms-printer"
CONFIG_FILE="$CONFIG_DIR/config.yaml"
DAEMON_FILE="$CONFIG_DIR/print_daemon.py"
LOG_FILE="/var/log/dashedcms-printer.log"

echo "==> DashedCMS print queue installer"
echo "    Printer:   $PRINTER_LABEL"
echo "    API URL:   $API_URL"
echo "    CUPS naam: $CUPS_NAME"
echo "    Systeem:   $OS"
echo ""

if [ "$(id -u)" -ne 0 ]; then
    echo "Run dit script met sudo: 'curl -fsSL <url> | sudo bash'"
    exit 1
fi

RUN_USER="${SUDO_USER:-$(whoami)}"

echo "==> Dependencies installeren..."
if [ "$OS" = "Darwin" ]; then
    # macOS: CUPS (lp/lpstat) en meestal python3 zitten er al. Geen apt.
    if ! command -v python3 >/dev/null 2>&1; then
        echo "    python3 ontbreekt. Installeer eerst de Command Line Tools: 'xcode-select --install' en draai dit script daarna opnieuw."
        exit 1
    fi
    python3 -m pip install --break-system-packages requests pyyaml >/dev/null 2>&1 \
        || python3 -m pip install requests pyyaml >/dev/null 2>&1 \
        || { python3 -m ensurepip >/dev/null 2>&1 && python3 -m pip install --break-system-packages requests pyyaml >/dev/null 2>&1; } \
        || echo "    LET OP: kon 'requests'/'pyyaml' niet automatisch installeren. Draai handmatig: sudo python3 -m pip install --break-system-packages requests pyyaml"
else
    apt update >/dev/null
    apt install -y cups-client python3 python3-pip python3-yaml curl >/dev/null
    pip3 install --break-system-packages requests pyyaml >/dev/null 2>&1 || pip3 install requests pyyaml >/dev/null
fi

PYTHON_BIN="$(command -v python3)"

echo ""
echo "==> Controleren of CUPS-printer '$CUPS_NAME' bestaat op deze host..."
if ! lpstat -p "$CUPS_NAME" >/dev/null 2>&1; then
    echo ""
    echo "    LET OP: er is op deze host nog geen CUPS-printer met de naam '$CUPS_NAME'."
    echo "    Beschikbare CUPS-printers op deze host:"
    lpstat -p 2>/dev/null | sed 's/^/      /' || echo "      (geen geconfigureerd)"
    echo ""
    echo "    De naam in het CMS moet exact gelijk zijn aan wat 'lpstat -p' hierboven toont."
    echo "    De daemon wordt nu wel geinstalleerd, maar print-jobs falen tot '$CUPS_NAME' bestaat."
    echo ""
fi

mkdir -p "$CONFIG_DIR"

echo "==> Daemon downloaden..."
curl -fsSL "$API_URL/vendor/dashed-ecommerce-core/pi/print_daemon.py" -o "$DAEMON_FILE"

if [ -f "$CONFIG_FILE" ]; then
    echo "==> Bestaande config gevonden; printer '$CUPS_NAME' toevoegen of vervangen..."
    MODE="merge"
else
    echo "==> Nieuwe config schrijven..."
    MODE="create"
fi

API_URL_ENV="$API_URL" TOKEN_ENV="$TOKEN" CUPS_NAME_ENV="$CUPS_NAME" CONFIG_FILE_ENV="$CONFIG_FILE" MODE_ENV="$MODE" "$PYTHON_BIN" <<'PYTHON_EOF'
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

if "printers" not in cfg:
    cfg["printers"] = []
    if cfg.get("token") and cfg.get("cups_printer"):
        cfg["printers"].append({"token": cfg.pop("token"), "cups_printer": cfg.pop("cups_printer")})
    else:
        cfg.pop("token", None)
        cfg.pop("cups_printer", None)

cfg["printers"] = [p for p in cfg["printers"] if p.get("cups_printer") != cups_name]
cfg["printers"].append({"token": token, "cups_printer": cups_name})
cfg["api_url"] = api_url

with config_path.open("w", encoding="utf-8") as f:
    yaml.safe_dump(cfg, f, default_flow_style=False, sort_keys=False)

print(f"    Config nu met {len(cfg['printers'])} printer(s):")
for p in cfg["printers"]:
    print(f"      - {p['cups_printer']}")
PYTHON_EOF

chown "$RUN_USER" "$CONFIG_FILE" 2>/dev/null || true
chmod 600 "$CONFIG_FILE"
touch "$LOG_FILE"; chmod 666 "$LOG_FILE" 2>/dev/null || true

if [ "$OS" = "Darwin" ]; then
    echo "==> launchd-service (her)starten..."
    PLIST="/Library/LaunchDaemons/com.dashedcms.printer.plist"
    cat > "$PLIST" <<PLIST_EOF
{!! '<' . '?xml version="1.0" encoding="UTF-8"?' . '>' !!}
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key><string>com.dashedcms.printer</string>
    <key>ProgramArguments</key>
    <array>
        <string>$PYTHON_BIN</string>
        <string>$DAEMON_FILE</string>
    </array>
    <key>EnvironmentVariables</key>
    <dict>
        <key>DASHEDCMS_PRINTER_CONFIG</key><string>$CONFIG_FILE</string>
        <key>DASHEDCMS_PRINTER_LOG</key><string>$LOG_FILE</string>
    </dict>
    <key>WorkingDirectory</key><string>$CONFIG_DIR</string>
    <key>RunAtLoad</key><true/>
    <key>KeepAlive</key><true/>
    <key>StandardOutPath</key><string>$LOG_FILE</string>
    <key>StandardErrorPath</key><string>$LOG_FILE</string>
</dict>
</plist>
PLIST_EOF
    chmod 644 "$PLIST"
    launchctl bootout system "$PLIST" >/dev/null 2>&1 || true
    launchctl bootstrap system "$PLIST" >/dev/null 2>&1 || launchctl load "$PLIST" >/dev/null 2>&1 || true
    launchctl enable system/com.dashedcms.printer >/dev/null 2>&1 || true
    launchctl kickstart -k system/com.dashedcms.printer >/dev/null 2>&1 || true
    sleep 2
    echo ""
    echo "==> Klaar! (macOS / launchd)"
    echo "    Live logs:   tail -f $LOG_FILE"
    echo "    Herstarten:  sudo launchctl kickstart -k system/com.dashedcms.printer"
    echo "    Stoppen:     sudo launchctl bootout system $PLIST"
else
    echo "==> systemd-service (her)starten..."
    SERVICE_FILE="/etc/systemd/system/dashedcms-printer.service"
    curl -fsSL "$API_URL/vendor/dashed-ecommerce-core/pi/dashedcms-printer.service" -o "$SERVICE_FILE"
    sed -i "s|^User=.*|User=$RUN_USER|" "$SERVICE_FILE"
    systemctl daemon-reload
    systemctl enable dashedcms-printer
    systemctl restart dashedcms-printer
    sleep 2
    echo ""
    echo "==> Klaar! (Linux / systemd)"
    systemctl status dashedcms-printer --no-pager --lines=5 || true
    echo "    Live logs:   sudo journalctl -u dashedcms-printer -f"
    echo "    Herstarten:  sudo systemctl restart dashedcms-printer"
fi

echo ""
echo "    Config:      sudo cat $CONFIG_FILE"
echo "    De CUPS-naam in het CMS moet exact zijn zoals 'lpstat -p' op deze host toont."
echo ""
