{!! '#!/bin/bash' !!}
{!! '# DashedCMS print queue installer (auto-discover all CUPS printers and register in CMS)' !!}
{!! '# Gegenereerd: ' . now()->toIso8601String() !!}
set -euo pipefail

API_URL="{{ $apiUrl }}"
DISCOVER_URL="{!! $discoverUrl !!}"

OS="$(uname -s)"
CONFIG_DIR="/opt/dashedcms-printer"
CONFIG_FILE="$CONFIG_DIR/config.yaml"
DAEMON_FILE="$CONFIG_DIR/print_daemon.py"
LOG_FILE="/var/log/dashedcms-printer.log"

echo "==> DashedCMS print queue auto-discover installer"
echo "    API URL:   $API_URL"
echo "    Systeem:   $OS"
echo ""

if [ "$(id -u)" -ne 0 ]; then
    echo "Run dit script met sudo: 'curl -fsSL <url> | sudo bash'"
    exit 1
fi

RUN_USER="${SUDO_USER:-$(whoami)}"
HOSTNAME_LABEL="$(hostname -s 2>/dev/null || hostname)"

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
echo "==> CUPS printers op deze host detecteren..."
CUPS_NAMES=()
while IFS= read -r _name; do
    [ -n "$_name" ] && CUPS_NAMES+=("$_name")
done < <(lpstat -p 2>/dev/null | awk '/^printer / {print $2}')

if [ "${#CUPS_NAMES[@]}" -eq 0 ]; then
    echo "    Geen CUPS printers gevonden op deze host."
    if [ "$OS" = "Darwin" ]; then
        echo "    Voeg er eerst een toe via Systeeminstellingen > Printers (of de CUPS web-UI op http://localhost:631)."
    else
        echo "    Registreer eerst minstens 1 printer:"
        echo "      USB:     sudo lpadmin -p <naam> -E -v 'usb://...' -m everywhere"
        echo "      Netwerk: sudo lpadmin -p <naam> -E -v socket://<ip>:9100 -m everywhere"
    fi
    echo "    Daarna draai dit commando opnieuw."
    exit 1
fi

echo "    ${#CUPS_NAMES[@]} CUPS printer(s) gevonden:"
for name in "${CUPS_NAMES[@]}"; do
    echo "      - $name"
done

echo ""
echo "==> Doorsturen naar CMS voor registratie..."
PAYLOAD=$(CUPS_LIST="$(printf '%s\n' "${CUPS_NAMES[@]}")" HOSTNAME_LABEL="$HOSTNAME_LABEL" "$PYTHON_BIN" - <<'PY'
import json
import os

names = [n for n in os.environ["CUPS_LIST"].splitlines() if n]
print(json.dumps({"hostname": os.environ.get("HOSTNAME_LABEL") or None, "discovered_printers": names}))
PY
)

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

CREATED_COUNT=$(RESPONSE_ENV="$RESPONSE" "$PYTHON_BIN" -c 'import json,os;print(len(json.loads(os.environ["RESPONSE_ENV"]).get("created",[])))')
SKIPPED_COUNT=$(RESPONSE_ENV="$RESPONSE" "$PYTHON_BIN" -c 'import json,os;print(len(json.loads(os.environ["RESPONSE_ENV"]).get("skipped",[])))')

echo "    $CREATED_COUNT nieuwe printer(s) aangemaakt, $SKIPPED_COUNT overgeslagen (bestonden al)."

if [ "$SKIPPED_COUNT" -gt 0 ]; then
    echo "    Skipped:"
    RESPONSE_ENV="$RESPONSE" "$PYTHON_BIN" - <<'PY'
import json
import os

for s in json.loads(os.environ["RESPONSE_ENV"]).get("skipped", []):
    print(f"      - {s.get('cups_name')}: {s.get('reason')}")
PY
fi

mkdir -p "$CONFIG_DIR"

echo ""
echo "==> Daemon downloaden..."
curl -fsSL "$API_URL/vendor/dashed-ecommerce-core/pi/print_daemon.py" -o "$DAEMON_FILE"

echo "==> Config schrijven (multi-printer)..."
API_URL_ENV="$API_URL" CONFIG_FILE_ENV="$CONFIG_FILE" RESPONSE_ENV="$RESPONSE" "$PYTHON_BIN" <<'PYTHON_EOF'
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

chown "$RUN_USER" "$CONFIG_FILE" 2>/dev/null || true
chmod 600 "$CONFIG_FILE"
touch "$LOG_FILE"; chmod 666 "$LOG_FILE" 2>/dev/null || true

if [ "$OS" = "Darwin" ]; then
    echo ""
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

    echo "==> Slaapstand uitzetten zolang op netstroom (anders stopt het printen)..."
    pmset -c sleep 0 disablesleep 1 >/dev/null 2>&1 || true

    sleep 2
    echo ""
    echo "==> Klaar! (macOS / launchd)"
    echo "    Live logs:    tail -f $LOG_FILE"
    echo "    Herstarten:   sudo launchctl kickstart -k system/com.dashedcms.printer"
    echo "    Stoppen:      sudo launchctl bootout system $PLIST"
    echo "    Slaap terug:  sudo pmset -c sleep 1 disablesleep 0"
else
    echo ""
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
    echo "    Live logs:    sudo journalctl -u dashedcms-printer -f"
    echo "    Herstarten:   sudo systemctl restart dashedcms-printer"
fi

echo ""
echo "    Nu in het CMS:"
echo "      1. Open Print queue -> Printers"
echo "      2. Voor elke nieuwe printer: stel het 'Doel' in (pakbon / verzendlabel / beide)"
echo "      3. Klik 'Test print' om end-to-end te testen"
echo ""
