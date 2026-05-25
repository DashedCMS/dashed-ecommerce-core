FROM debian:bookworm-slim

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y --no-install-recommends \
    cups \
    cups-bsd \
    cups-client \
    cups-filters \
    printer-driver-all \
    python3 \
    python3-pip \
    python3-yaml \
    python3-requests \
    curl \
    jq \
    avahi-daemon \
    avahi-utils \
    libnss-mdns \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/* \
    && pip3 install --break-system-packages requests pyyaml >/dev/null 2>&1 || true

RUN sed -i 's/^Listen localhost:631/Listen 0.0.0.0:631/' /etc/cups/cupsd.conf \
    && echo "ServerAlias *" >> /etc/cups/cupsd.conf

COPY print_daemon.py /opt/dashedcms-printer/print_daemon.py
COPY entrypoint.sh /opt/dashedcms-printer/entrypoint.sh
RUN chmod +x /opt/dashedcms-printer/entrypoint.sh

VOLUME ["/opt/dashedcms-printer/state", "/etc/cups", "/var/spool/cups"]

EXPOSE 631

ENV DASHEDCMS_PRINTER_CONFIG=/opt/dashedcms-printer/state/config.yaml
ENV DASHEDCMS_PRINTER_LOG=/opt/dashedcms-printer/state/daemon.log

ENTRYPOINT ["/opt/dashedcms-printer/entrypoint.sh"]
