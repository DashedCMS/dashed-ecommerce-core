---
title: Bestelstatussen
description: Overzicht van alle bestelstatussen en hun betekenis
---

# Bestelstatussen

Elke bestelling doorloopt een aantal statussen. Hier is een overzicht van wat elke status betekent.

## Statussen

| Status | Betekenis |
|--------|-----------|
| **Pending** | Bestelling is aangemaakt, betaling is nog niet ontvangen |
| **Betaald** | Betaling is succesvol ontvangen |
| **Wacht op bevestiging** | Bestelling wacht op handmatige bevestiging |
| **Verzonden** | Bestelling is verzonden naar de klant |
| **Geannuleerd** | Bestelling is geannuleerd |

## Automatische statuswijzigingen

- Bij een succesvolle betaling wordt de status automatisch op **"Betaald"** gezet
- Bestellingen die langer dan 3 uur op **"Pending"** staan worden automatisch geannuleerd
- Bij het toevoegen van een track & trace code wijzigt de status automatisch naar **"Verzonden"**

## E-mail notificaties

Bij elke statuswijziging kan automatisch een e-mail naar de klant worden verstuurd:
- **Betaald** - Bestelbevestiging
- **Verzonden** - Verzendbevestiging met track & trace
- **Geannuleerd** - Annuleringsbevestiging
