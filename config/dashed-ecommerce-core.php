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
];
