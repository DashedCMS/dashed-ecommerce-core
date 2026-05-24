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
