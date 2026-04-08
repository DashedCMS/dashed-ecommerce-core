---
title: Verlaten winkelwagen
description: Automatische e-mails voor verlaten winkelwagens
---

# Verlaten winkelwagen

Het verlaten winkelwagen systeem stuurt automatisch e-mails naar klanten die producten in hun winkelwagen hebben achtergelaten zonder af te rekenen.

## Hoe werkt het?

1. Een klant vult zijn e-mailadres in bij de checkout
2. Het systeem slaat dit op bij de winkelwagen
3. Als de klant niet afrekent, worden na de ingestelde vertraging automatisch e-mails verstuurd
4. De klant ontvangt een link waarmee de winkelwagen wordt hersteld

## Email flows

Ga naar **E-commerce > Verlaten winkelwagen** om email flows te beheren.

### Een flow aanmaken
Klik op **"Maak standaard flow aan"** voor een voorgeconfigureerde flow met 3 stappen, of maak een eigen flow aan.

### Stappen configureren
Elke stap heeft:
- **Vertraging** - Hoelang na het verlaten van de winkelwagen de email wordt verstuurd
- **Onderwerp** - De onderwerpregel (variabelen: `:product:`, `:siteName:`, `:cartTotal:`)
- **Inhoud blokken** - Bouw de email op met blokken (tekst, product, producten, review, korting, USPs, knop)
- **Kortingscode** - Optioneel een automatisch gegenereerde kortingscode toevoegen

### Bloktypen
- **Tekst** - Vrije tekst met variabelen
- **Hoofdproduct** - Toont het eerste product uit de winkelwagen
- **Alle producten** - Toont alle producten uit de winkelwagen
- **Klantreview** - Toont een willekeurige 5-sterren review
- **Kortingscode** - Toont de gegenereerde kortingscode
- **USPs** - Toont unique selling points
- **Knop** - Call-to-action knop naar de checkout
- **Scheidingslijn** - Visuele scheiding

### Meertaligheid
De inhoud van elke stap (onderwerp en blokken) is per taal instelbaar via de taalkeuze.

## Kortingscode prefix
Per flow kun je de prefix van gegenereerde kortingscodes instellen (standaard: "TERUG"). Codes worden automatisch aangemaakt bij het versturen.

## Statistieken
Op de flow pagina zie je statistieken:
- **Verzonden** - Aantal verstuurde e-mails
- **Geklikt** - Hoeveel ontvangers op een link hebben geklikt
- **Conversies** - Hoeveel klanten daadwerkelijk hebben besteld
- **Omzet** - Totale omzet van herstelde bestellingen
- **Knop kliks** / **Product kliks** - Welk type link het meest wordt geklikt

## Test e-mail
Bij elke stap kun je een test e-mail versturen naar jezelf via de **"Stuur test"** knop.
