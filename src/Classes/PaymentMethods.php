<?php

namespace Qubiqx\QcommerceEcommerceCore\Classes;

use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceEcommerceCore\Models\PaymentMethod;

class PaymentMethods
{
    //Todo: 2 functions below must be 1 function inside ShoppingCart class
//    public static function getAvailablePaymentMethods($countryName)
//    {
//        $paymentMethods = self::getPaymentMethods();
//        $shippingZones = ShippingZone::get();
//        foreach ($shippingZones as $shippingZone) {
//            $shippingZoneIsActive = false;
//            foreach (json_decode($shippingZone->zones, true) as $zone) {
//                foreach (Countries::getCountries() as $country) {
//                    if ($country['name'] == $zone['id']) {
//                        if (strtolower($country['name']) == strtolower($countryName)) {
//                            $shippingZoneIsActive = true;
//                        }
//                        if (strtolower($country['alpha2Code']) == strtolower($countryName)) {
//                            $shippingZoneIsActive = true;
//                        }
//                        if (strtolower($country['alpha3Code']) == strtolower($countryName)) {
//                            $shippingZoneIsActive = true;
//                        }
//                        if (strtolower($country['demonym']) == strtolower($countryName)) {
//                            $shippingZoneIsActive = true;
//                        }
//                        foreach ($country['altSpellings'] as $altSpelling) {
//                            if (strlen($countryName) > 5) {
//                                if (Str::contains(strtolower($altSpelling), strtolower($countryName))) {
//                                    $shippingZoneIsActive = true;
//                                }
//                            } else {
//                                if (strtolower($altSpelling) == strtolower($countryName)) {
//                                    $shippingZoneIsActive = true;
//                                }
//                            }
//                        }
//                    }
//                }
//            }
//
//            if (!$shippingZoneIsActive && $shippingZone->search_fields) {
//                $searchFields = explode(',', $shippingZone->search_fields);
//                foreach ($searchFields as $searchField) {
//                    if (strtolower($searchField) == strtolower($countryName)) {
//                        $shippingZoneIsActive = true;
//                    }
//                }
//            }
//
//            if ($shippingZoneIsActive && $shippingZone->disabled_payment_method_ids) {
//                $disabledPaymentMethodIds = json_decode($shippingZone->disabled_payment_method_ids, true);
//                if (is_array($disabledPaymentMethodIds)) {
//                    foreach ($disabledPaymentMethodIds as $disabledPaymentMethod) {
//                        foreach ($paymentMethods as $key => $paymentMethod) {
//                            if ($disabledPaymentMethod['id'] == $paymentMethod['id']) {
//                                unset($paymentMethods[$key]);
//                            }
//                        }
//                    }
//                }
//            }
//        }
//
//        return $paymentMethods;
//    }
//
//    public static function getPaymentMethods()
//    {
//        $paymentMethods = [];
//        foreach (PaymentMethod::where('available_from_amount', '<', self::total())->get() as $paymentMethod) {
//            $paymentMethods[] = [
//                'id' => $paymentMethod->id,
//                'system' => 'own',
//                'name' => $paymentMethod->name,
//                'image' => [],
//                'postpay' => false,
//                'extra_costs' => $paymentMethod->extra_costs,
//                'additional_info' => $paymentMethod->additional_info,
//                'payment_instructions' => $paymentMethod->payment_instructions,
//                'deposit_calculation' => $paymentMethod->deposit_calculation
//            ];
//        }
//
//        if (Customsetting::get('mollie_connected')) {
    ////            foreach (Mollie::getPaymentMethods() as $paymentMethod) {
    ////                if ($paymentMethod->active) {
    ////                    $paymentMethods[] = [
    ////                        'id' => 'mollie_' . $paymentMethod->id,
    ////                        'system' => 'mollie',
    ////                        'image' => [],
    ////                        'name' => $paymentMethod->description,
    ////                        'postpay' => false,
    ////                        'extra_costs' => Customsetting::get('mollie_payment_method_costs_' . $paymentMethod->id, Sites::getActive(), 0),
    ////                        'additional_info' => '',
    ////                        'payment_instructions' => '',
    ////                        'deposit_calculation' => ''
    ////                    ];
    ////                }
    ////            }
//        }
//
//        if (Customsetting::get('paynl_connected')) {
    ////            foreach (PayNL::getPaymentMethods() as $paymentMethod) {
    ////                if ($paymentMethod['active'] && ($paymentMethod['min_amount'] / 100) <= self::total() && ($paymentMethod['max_amount'] / 100) >= self::total()) {
    ////                    $paymentMethods[] = [
    ////                        'id' => 'paynl_' . $paymentMethod['id'],
    ////                        'system' => 'paynl',
    ////                        'image' => [
    ////                            'original' => 'https://static.pay.nl/' . $paymentMethod['brand']['image'],
    ////                            '20' => Thumbnail::src('https://static.pay.nl/' . $paymentMethod['brand']['image'])->widen(20)->url(true),
    ////                            '25' => Thumbnail::src('https://static.pay.nl/' . $paymentMethod['brand']['image'])->widen(25)->url(true),
    ////                            '50' => Thumbnail::src('https://static.pay.nl/' . $paymentMethod['brand']['image'])->widen(50)->url(true),
    ////                            '100' => Thumbnail::src('https://static.pay.nl/' . $paymentMethod['brand']['image'])->widen(100)->url(true),
    ////                        ],
    ////                        'name' => $paymentMethod['visibleName'],
    ////                        'postpay' => $paymentMethod['postpay'],
    ////                        'extra_costs' => Customsetting::get('paynl_payment_method_costs_' . $paymentMethod['id'], Sites::getActive(), 0),
    ////                        'additional_info' => Customsetting::get('paynl_payment_method_additional_info_' . $paymentMethod['id'], Sites::getActive()),
    ////                        'payment_instructions' => Customsetting::get('paynl_payment_method_payment_instructions_' . $paymentMethod['id'], Sites::getActive()),
    ////                        'deposit_calculation' => ''
    ////                    ];
    ////                }
    ////            }
//        }
//
//        return $paymentMethods;
//    }

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

//    public static function getPaymentMethodsForDeposit($paymentMethodId)
//    {
//        $paymentMethod = PaymentMethod::find($paymentMethodId);
//
//        $depositPaymentMethods = PaymentMethod::find($paymentMethod->deposit_calculation_payment_method_ids);
//
//        return $depositPaymentMethods;
//    }
}
