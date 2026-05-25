{!! '#!/bin/bash' !!}
{!! '# DashedCMS print queue Docker installer' !!}
{!! '# Generated: ' . now()->toIso8601String() !!}
set -euo pipefail

API_URL="{{ $apiUrl }}"
PAIRING_CODE="{{ $pairingCode }}"
INSTALL_DIR="${INSTALL_DIR:-/opt/dashedcms-printer}"

echo "==> DashedCMS print queue Docker installer"
echo "    API URL:      $API_URL"
echo "    Pairing code: $PAIRING_CODE"
echo "    Install dir:  $INSTALL_DIR"
echo ""

if ! command -v docker >/dev/null 2>&1; then
    echo "ERROR: docker is niet geinstalleerd. Installeer Docker via je NAS package manager (Container Manager / Container Station / Docker plugin) en draai dit script opnieuw."
    exit 1
fi

DOCKER_COMPOSE="docker compose"
if ! docker compose version >/dev/null 2>&1; then
    if command -v docker-compose >/dev/null 2>&1; then
        DOCKER_COMPOSE="docker-compose"
    else
        echo "ERROR: docker compose plugin niet beschikbaar."
        exit 1
    fi
fi

if [ "$(id -u)" -ne 0 ]; then
    SUDO="sudo"
else
    SUDO=""
fi

$SUDO mkdir -p "$INSTALL_DIR"
cd "$INSTALL_DIR"

echo "==> Bron-bestanden downloaden..."
$SUDO curl -fsSL "$API_URL/vendor/dashed-ecommerce-core/pi/docker/Dockerfile" -o Dockerfile
$SUDO curl -fsSL "$API_URL/vendor/dashed-ecommerce-core/pi/docker/entrypoint.sh" -o entrypoint.sh
$SUDO curl -fsSL "$API_URL/vendor/dashed-ecommerce-core/pi/print_daemon.py" -o print_daemon.py
$SUDO chmod +x entrypoint.sh

# USB device passthrough alleen als de host /dev/bus/usb heeft
USB_LINE=""
if [ -e /dev/bus/usb ]; then
    USB_LINE="    devices:
      - /dev/bus/usb:/dev/bus/usb"
fi

# Network printers via env var (laat leeg of vul in: name1=socket://1.2.3.4:9100,name2=ipp://...)
NETWORK_PRINTERS_VAL="${NETWORK_PRINTERS:-}"

echo "==> docker-compose.yml schrijven..."
$SUDO tee docker-compose.yml >/dev/null <<COMPOSE_EOF
services:
  dashedcms-printer:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: dashedcms-printer
    restart: unless-stopped
    network_mode: host
    environment:
      API_URL: "$API_URL"
      PAIRING_CODE: "$PAIRING_CODE"
      HOSTNAME_LABEL: "$(hostname -s 2>/dev/null || hostname)"
      NETWORK_PRINTERS: "$NETWORK_PRINTERS_VAL"
    volumes:
      - state:/opt/dashedcms-printer/state
      - cups-config:/etc/cups
      - cups-spool:/var/spool/cups
${USB_LINE}

volumes:
  state:
  cups-config:
  cups-spool:
COMPOSE_EOF

echo "==> Container builden en starten..."
$SUDO $DOCKER_COMPOSE up -d --build

echo ""
echo "==> Logs (Ctrl+C om te stoppen, container blijft draaien):"
$SUDO $DOCKER_COMPOSE logs -f --tail=50 &
LOGS_PID=$!
sleep 20
kill $LOGS_PID 2>/dev/null || true

echo ""
echo "==> Klaar!"
echo "    Container draait op de host. CUPS web UI: http://<nas-ip>:631"
echo "    Live logs:     $SUDO $DOCKER_COMPOSE -f $INSTALL_DIR/docker-compose.yml logs -f"
echo "    Stop:          $SUDO $DOCKER_COMPOSE -f $INSTALL_DIR/docker-compose.yml down"
echo "    Restart:       $SUDO $DOCKER_COMPOSE -f $INSTALL_DIR/docker-compose.yml restart"
echo ""
echo "    Netwerkprinters toevoegen na pairing:"
echo "      1. Open http://<nas-ip>:631 in je browser (CUPS web UI)"
echo "      2. Administration -> Add Printer"
echo "      3. Kies socket://<ip>:9100 (HP/Brother) of ipp://<ip>:631 (IPP)"
echo "      4. Of bewerk $INSTALL_DIR/docker-compose.yml: zet NETWORK_PRINTERS"
echo "         (formaat: name1=socket://1.2.3.4:9100,name2=ipp://1.2.3.5)"
echo "         en draai '$DOCKER_COMPOSE up -d' opnieuw."
echo ""
