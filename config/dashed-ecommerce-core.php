<?php

return [
    'registerDefaultBuilderBlocks' => true,
    'debug_logs_enabled' => env('DASHED_ECOMMERCE_DEBUG_LOGS_ENABLED', false),

    'invoices' => [
        // Facturen en pakbonnen staan prive op de schijf; de downloadroute is
        // de enige weg ernaartoe en werkt op de orderhash (32 willekeurige
        // tekens, achter de verzoeklimiet dashed-order-pages). Op true eist de
        // route ook de handtekening die het systeem in zijn links zet, tenzij
        // de klant van de bestelling of een beheerder is ingelogd. Dan werken
        // oude, onondertekende links uit eerder verstuurde mails niet meer
        // voor gasten. Zie Classes\InvoiceAccess.
        'require_signature' => env('DASHED_INVOICE_REQUIRE_SIGNATURE', false),
    ],

    'security' => [
        // Pincode, los van wachtwoord en MFA, die verplicht is om een order
        // handmatig op betaald te zetten ("Voeg betaling toe", "Registreer
        // handmatige betaling", en de app). Leeg = geen pincode. Zie
        // Classes\ManualPaymentPin.
        'manual_paid_pin' => (string) env('MANUAL_PAID_PIN', ''),

        // Een betaalmethode met psp own (handmatige betaling, kost niets en
        // loopt langs geen PSP) mag alleen actief zijn in de checkout als dit
        // aanstaat. Op false zet PaymentMethod::saving zo'n methode uit.
        'allow_own_psp_in_checkout' => (bool) env('DASHED_ALLOW_OWN_PSP_IN_CHECKOUT', true),

        // Melding als een gebruiker in tien minuten zoveel orders handmatig
        // op betaald zet; een mail per uur.
        'alert_marked_paid_per_10_min' => (int) env('ALERT_MARKED_PAID_PER_10_MIN', 10),

        // Melding "prijs drastisch verlaagd" als de nieuwe prijs onder deze
        // fractie van de oude komt.
        'alert_price_drop_fraction' => (float) env('ALERT_PRICE_DROP_FRACTION', 0.5),
    ],
];
