# Google Ads Customer Match feed

Een HTTPS-endpoint dat Google Ads periodiek ophaalt om Customer Match audiences bij te werken met betaalde klanten van de shop. Alle PII (email, telefoon, naam) wordt SHA-256 gehasht voor het verlaat de server.

## Beheer in de admin

Ga naar **Instellingen → Google Ads Customer Match**.

### Velden

- **URL**: kopieer deze in Google Ads bij "HTTPS source".
- **Gebruikersnaam**: vaste waarde, basic-auth user.
- **Wachtwoord**: alleen 1x getoond na **Genereer nieuw wachtwoord**. Kopieer direct.
- **Slug**: random deel van de URL. Roteer via **Roteer URL** als de URL gelekt is.
- **Endpoint actief**: uit = endpoint geeft 404. Niet zichtbaar voor Google Ads.

### Filter

- **Minimum aantal betaalde bestellingen**: standaard 1.
- **Landen**: ISO-2 codes (NL, BE, DE). Leeg = alle landen.
- **Vanaf besteldatum** / **Tot besteldatum**: optioneel venster.

### Acties

- **Roteer URL** — nieuwe slug, oude werkt niet meer.
- **Genereer nieuw wachtwoord** — nieuwe credentials, oude werkt niet meer. Notification toont het wachtwoord 1x.
- **Preview eerste 5 rijen** — dry-run om te zien of het filter klanten oplevert.

## Data formaat

CSV met header: `Email,Phone,"First Name","Last Name",Country,Zip`.

| Kolom       | Bron                                               | Normalisatie               | Hashed |
| ----------- | -------------------------------------------------- | -------------------------- | ------ |
| Email       | `orders.email`                                     | trim + lowercase           | ja     |
| Phone       | `orders.phone_number` + land voor dial-code        | digits + E.164 met `+`     | ja     |
| First Name  | `invoice_first_name` of `first_name`               | trim + lowercase           | ja     |
| Last Name   | `invoice_last_name` of `last_name`                 | trim + lowercase           | ja     |
| Country     | `invoice_country` of `country`                     | ISO-2 uppercase            | nee    |
| Zip         | `invoice_zip_code` of `zip_code`                   | trim                       | nee    |

Lege bron = lege kolom (niet hash van leeg).

## Bron van klanten

Geaggregeerd uit `orders` (alleen statussen die `Order::scopeIsPaid` matcht: `paid`, `waiting_for_confirmation`, `partially_paid`), gededupliceerd op lowercase `email`. Per klant worden de meest recente PII-velden gebruikt.

## Stappenplan in Google Ads

1. Ga naar **Tools → Shared library → Audience manager → Segments**.
2. Klik **+** → **Customer list**.
3. Kies **Upload list with hashed data**.
4. Kies bij upload-methode **HTTPS schedule**.
5. Plak de URL uit de admin.
6. Vul gebruikersnaam + wachtwoord in.
7. Selecteer schedule (dagelijks aanbevolen).
8. Sla op. Google doet eerst een test-fetch — controleer in de admin onder "Activiteit".

## Security

- Basic-auth via `Hash::check` met constant-time vergelijking.
- HTTPS verplicht in productie (`$request->isSecure()`).
- Rate limit 10 requests/min per IP.
- Alle requests gelogd in `customer_match_access_logs` (status, IP, user-agent, row count).
- Wachtwoord nooit decryptbaar opgeslagen — bij verlies: regenereren.

## Routing

Route geregistreerd in `packages/dashed/dashed-ecommerce-core/routes/google-ads.php` via spatie's `hasRoutes(['google-ads'])` in de service provider. Middleware: `throttle:google-ads-customer-match`, `GoogleAdsBasicAuth`.

## Trust proxies

Achter een load balancer (Forge/Sail) checkt `$request->isSecure()` op `X-Forwarded-Proto`. Zorg dat de consumer-app `TrustProxies` configureert met `protected $proxies = '*'` of de specifieke LB-range.
