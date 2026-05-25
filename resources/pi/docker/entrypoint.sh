#!/bin/bash
set -eu

STATE_DIR="/opt/dashedcms-printer/state"
CONFIG_FILE="${DASHEDCMS_PRINTER_CONFIG:-$STATE_DIR/config.yaml}"
mkdir -p "$STATE_DIR"

echo "==> DashedCMS print daemon container starting"

start_cups() {
    if pgrep -x cupsd >/dev/null; then return; fi
    /usr/sbin/cupsd
    for _ in $(seq 1 20); do
        if lpstat -r >/dev/null 2>&1; then return; fi
        sleep 0.5
    done
    echo "ERROR: CUPS did not start" >&2
    exit 1
}

register_usb_printers() {
    local lines names_var=$1 uris_var=$2
    lines=$(lpinfo -v 2>/dev/null | grep '^direct usb://' || true)
    if [ -z "$lines" ]; then return; fi
    local idx=1
    while IFS= read -r line; do
        local uri name
        uri=$(echo "$line" | awk '{print $2}')
        [ -z "$uri" ] && continue
        name="dashed_usb_${idx}"
        echo "    USB [$idx]: $uri -> $name"
        lpadmin -p "$name" -E -v "$uri" -m everywhere 2>/dev/null \
            || lpadmin -p "$name" -E -v "$uri" -m drv:///sample.drv/laserjet.ppd
        cupsenable "$name" 2>/dev/null || true
        cupsaccept "$name" 2>/dev/null || true
        eval "$names_var+=(\"$name\")"
        eval "$uris_var+=(\"$uri\")"
        idx=$((idx + 1))
    done <<< "$lines"
}

register_network_printers() {
    local names_var=$1 uris_var=$2
    [ -z "${NETWORK_PRINTERS:-}" ] && return
    IFS=',' read -ra entries <<< "$NETWORK_PRINTERS"
    for entry in "${entries[@]}"; do
        local name uri
        name=$(echo "$entry" | cut -d= -f1 | xargs)
        uri=$(echo "$entry" | cut -d= -f2- | xargs)
        [ -z "$name" ] || [ -z "$uri" ] && continue
        echo "    Network: $uri -> $name"
        lpadmin -p "$name" -E -v "$uri" -m everywhere 2>/dev/null \
            || lpadmin -p "$name" -E -v "$uri" -m drv:///sample.drv/laserjet.ppd
        cupsenable "$name" 2>/dev/null || true
        cupsaccept "$name" 2>/dev/null || true
        eval "$names_var+=(\"$name\")"
        eval "$uris_var+=(\"$uri\")"
    done
}

start_cups

if [ ! -f "$CONFIG_FILE" ]; then
    if [ -z "${API_URL:-}" ] || [ -z "${PAIRING_CODE:-}" ]; then
        echo "ERROR: First run needs API_URL and PAIRING_CODE env vars" >&2
        exit 1
    fi

    echo "==> First run, pairing met $API_URL"
    declare -a CUPS_NAMES=()
    declare -a DEVICE_URIS=()

    if [ -e /dev/bus/usb ]; then
        register_usb_printers CUPS_NAMES DEVICE_URIS
    else
        echo "    Geen /dev/bus/usb gemount, sla USB-detectie over"
    fi

    register_network_printers CUPS_NAMES DEVICE_URIS

    if [ ${#CUPS_NAMES[@]} -eq 0 ]; then
        echo "    Geen printers gedetecteerd. Container pair't toch zodat je later printers kunt toevoegen via CUPS-UI (port 631)."
    fi

    PAYLOAD=$(jq -n \
        --arg code "$PAIRING_CODE" \
        --arg host "${HOSTNAME_LABEL:-$(hostname)}" \
        --argjson printers "$(jq -n \
            --argjson names "$(printf '%s\n' "${CUPS_NAMES[@]+"${CUPS_NAMES[@]}"}" | jq -R . | jq -s .)" \
            --argjson uris  "$(printf '%s\n' "${DEVICE_URIS[@]+"${DEVICE_URIS[@]}"}" | jq -R . | jq -s .)" \
            '[range(0; ($names|length)) | {cups_name: $names[.], device_uri: $uris[.]}]')" \
        '{pairing_code: $code, hostname: $host, discovered_printers: $printers}')

    RESPONSE=$(curl -fsS -X POST "$API_URL/api/print/pair" \
        -H 'Accept: application/json' \
        -H 'Content-Type: application/json' \
        -d "$PAYLOAD") || {
            echo "Pairing call gefaald. Check API_URL en pairing code." >&2
            exit 1
        }

    TOKEN=$(echo "$RESPONSE" | jq -r '.token // empty')
    CUPS_NAME=$(echo "$RESPONSE" | jq -r '.cups_name // empty')

    if [ -z "$TOKEN" ]; then
        echo "Geen token ontvangen. CMS response: $RESPONSE" >&2
        exit 1
    fi

    : "${CUPS_NAME:=${CUPS_NAMES[0]:-placeholder}}"

    cat > "$CONFIG_FILE" <<CONFIG_EOF
api_url: $API_URL
token: "$TOKEN"
cups_printer: $CUPS_NAME
poll_interval_seconds: 5
log_level: INFO
CONFIG_EOF
    chmod 600 "$CONFIG_FILE"
    echo "==> Pairing succesvol. Actieve CUPS-printer: $CUPS_NAME"
else
    echo "==> Config bestaat al; sla pairing over"
    register_network_printers _SKIP_NAMES _SKIP_URIS
fi

echo "==> Daemon starten"
exec python3 /opt/dashedcms-printer/print_daemon.py
