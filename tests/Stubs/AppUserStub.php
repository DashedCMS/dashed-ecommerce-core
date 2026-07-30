<?php

declare(strict_types=1);

/**
 * Sommige dashed-core/dashed-ecommerce-core migraties (bijv.
 * 2025_02_15_133341_add_user_id_to_discount_codes.php) verwijzen hardcoded
 * naar \App\Models\User, ervan uitgaande dat ze in een echte Laravel-app
 * draaien. De Testbench-skeleton-app die dit package voor tests gebruikt
 * heeft die klasse niet, dus zonder deze stub crasht elke test die de
 * migraties boot met "Class App\Models\User not found".
 *
 * Alleen gedefinieerd als de klasse nog niet bestaat (bijv. wanneer dit ooit
 * wel binnen een echte app-context draait).
 */
if (! class_exists(\App\Models\User::class)) {
    class_alias(\Illuminate\Foundation\Auth\User::class, \App\Models\User::class);
}
