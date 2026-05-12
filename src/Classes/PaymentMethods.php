<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Filament\Facades\Filament;
use Dashed\DashedCore\Classes\Mails;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Filament\Resources\PaymentMethodResource;

class PaymentMethods
{
    public static function get()
    {
        $paymentMethods = [];
        foreach (PaymentMethod::get() as $paymentMethod) {
            $paymentMethods[] = [
                'id' => $paymentMethod->id,
                'system' => 'own',
                'name' => $paymentMethod->name,
                'image' => [],
                'postpay' => false,
                'extra_costs' => $paymentMethod->extra_costs,
                'additional_info' => $paymentMethod->additional_info,
                'payment_instructions' => $paymentMethod->payment_instructions,
                'deposit_calculation' => $paymentMethod->deposit_calculation,
            ];
        }

        return $paymentMethods;
    }

    public static function getTypes()
    {
        return [
            'online' => 'Online',
            'pos' => 'Point of Sale',
        ];
    }

    public static function notifyAdminsOfNewPaymentMethod(PaymentMethod $paymentMethod): void
    {
        $editUrl = null;

        try {
            $panel = Filament::getPanel('dashed');
            $editUrl = PaymentMethodResource::getUrl(
                name: 'edit',
                parameters: ['record' => $paymentMethod->id],
                panel: $panel,
            );
        } catch (\Throwable $e) {
            $editUrl = null;
        }

        $name = $paymentMethod->getTranslation('name', app()->getLocale(), false)
            ?: ($paymentMethod->name ?: $paymentMethod->psp_id);
        $psp = $paymentMethod->psp;

        $subject = "Nieuwe betaalmethode gesynced: {$name} ({$psp})";

        $content = "Er is een nieuwe betaalmethode gesynced via {$psp}: <strong>{$name}</strong>. "
            . "Deze staat standaard uit en moet handmatig geactiveerd worden.";

        if ($editUrl) {
            $content .= " <a href=\"{$editUrl}\">Open betaalmethode in CMS</a>";
        }

        Mails::sendNotificationToAdmins($content, $subject);
    }
}
