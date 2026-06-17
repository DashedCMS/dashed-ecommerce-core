# DashedCMS Print Daemon

Python daemon voor Raspberry Pi die pakbonnen en verzendlabels print via CUPS.

## Installatie

sudo apt update
sudo apt install -y cups python3 python3-pip python3-yaml
sudo lpadmin -p pakbon_brother -E -v usb://... -m drv:///sample.drv/laserjet.ppd
sudo cupsctl ServerAlias=*

sudo mkdir -p /opt/dashedcms-printer
sudo cp print_daemon.py config.example.yaml /opt/dashedcms-printer/
sudo cp dashedcms-printer.service /etc/systemd/system/
sudo pip3 install -r requirements.txt

## Configuratie

1. Genereer een token in het CMS via Admin -> Print queue -> Printers -> [printer] -> Genereer token.
2. Kopieer config.example.yaml naar /opt/dashedcms-printer/config.yaml en vul de token + CUPS printer naam in.

## Service starten

sudo systemctl daemon-reload
sudo systemctl enable dashedcms-printer
sudo systemctl start dashedcms-printer
sudo systemctl status dashedcms-printer

## Logs

sudo journalctl -u dashedcms-printer -f
sudo tail -f /var/log/dashedcms-printer.log

## macOS (launchd) — alternatief voor de Raspberry Pi

Een oude Mac (mini, iMac of MacBook) met macOS werkt prima als print-host: CUPS zit al in macOS ingebouwd. In plaats van systemd gebruik je launchd.

### Installatie

brew install python
pip3 install requests pyyaml

Koppel de printer (USB of netwerk) via Systeeminstellingen -> Printers, of via de command line, en controleer de CUPS-naam:

lpstat -p -d

Plaats de daemon-bestanden:

sudo mkdir -p /opt/dashedcms-printer
sudo cp print_daemon.py config.example.yaml /opt/dashedcms-printer/

Maak config.yaml aan zoals onder "Configuratie" beschreven (token + CUPS printernaam uit lpstat).

### Service starten

sudo cp com.dashedcms.printer.plist /Library/LaunchDaemons/
sudo launchctl load -w /Library/LaunchDaemons/com.dashedcms.printer.plist

### Slaapstand voorkomen

Zorg dat de Mac niet in slaapstand gaat terwijl hij op netstroom hangt, anders stopt de daemon:

sudo pmset -c sleep 0 disablesleep 1

### Herstarten / stoppen

sudo launchctl unload /Library/LaunchDaemons/com.dashedcms.printer.plist
sudo launchctl load -w /Library/LaunchDaemons/com.dashedcms.printer.plist

### Logs

tail -f /var/log/dashedcms-printer.log
