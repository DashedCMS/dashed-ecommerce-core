<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\User;

return new class extends Migration {
    public function up(): void
    {
        User::query()->chunkById(100, function ($users) {

            foreach ($users as $user) {

                $lastOrder = $user?->lastOrderFromAllOrders();

                if (!$lastOrder) {
                    continue;
                }

                $updated = false;

                // Contact
                if (!$user->phone_number && $lastOrder->phone_number) {
                    $user->phone_number = $lastOrder->phone_number;
                    $updated = true;
                }

                if (!$user->date_of_birth && $lastOrder->date_of_birth) {
                    $user->date_of_birth = $lastOrder->date_of_birth;
                    $updated = true;
                }

                if (!$user->gender && $lastOrder->gender) {
                    $user->gender = $lastOrder->gender;
                    $updated = true;
                }

                // Shipping
                if (!$user->street && $lastOrder->street) {
                    $user->street = $lastOrder->street;
                    $updated = true;
                }

                if (!$user->house_nr && $lastOrder->house_nr) {
                    $user->house_nr = $lastOrder->house_nr;
                    $updated = true;
                }

                if (!$user->zip_code && $lastOrder->zip_code) {
                    $user->zip_code = $lastOrder->zip_code;
                    $updated = true;
                }

                if (!$user->city && $lastOrder->city) {
                    $user->city = $lastOrder->city;
                    $updated = true;
                }

                if (!$user->country && $lastOrder->country) {
                    $user->country = $lastOrder->country;
                    $updated = true;
                }

                // Company
                if (!$user->is_company && $lastOrder->is_company) {
                    $user->is_company = $lastOrder->is_company;
                    $updated = true;
                }

                if (!$user->company && $lastOrder->company) {
                    $user->company = $lastOrder->company;
                    $updated = true;
                }

                if (!$user->tax_id && $lastOrder->tax_id) {
                    $user->tax_id = $lastOrder->tax_id;
                    $updated = true;
                }

                // Invoice
                if (!$user->invoice_street && $lastOrder->invoice_street) {
                    $user->invoice_street = $lastOrder->invoice_street;
                    $updated = true;
                }

                if (!$user->invoice_house_nr && $lastOrder->invoice_house_nr) {
                    $user->invoice_house_nr = $lastOrder->invoice_house_nr;
                    $updated = true;
                }

                if (!$user->invoice_zip_code && $lastOrder->invoice_zip_code) {
                    $user->invoice_zip_code = $lastOrder->invoice_zip_code;
                    $updated = true;
                }

                if (!$user->invoice_city && $lastOrder->invoice_city) {
                    $user->invoice_city = $lastOrder->invoice_city;
                    $updated = true;
                }

                if (!$user->invoice_country && $lastOrder->invoice_country) {
                    $user->invoice_country = $lastOrder->invoice_country;
                    $updated = true;
                }

                if ($updated) {
                    $user->save();
                }
            }
        });
    }

    public function down(): void
    {
        // Bewust leeg — we willen geen data verwijderen bij rollback
    }
};
