<?php

namespace Dashed\DashedEcommerceCore\Classes;

class TikTokHelper
{
    public static function getShoppingCartItems($cartTotal = null, ?string $email = null, ?string $phoneNumber = null): array
    {
        if (! cartHelper()->isInitialized()) {
            cartHelper()->setTotal();
        }

        if (! $cartTotal) {
            $cartTotal = cartHelper()->getTotal();
        }

        $items = [];

        foreach (cartHelper()->getCartItems() as $cartItem) {
            if ($cartItem->model) {
                $items[] = [
                    'content_id' => $cartItem->model->id,
                    'content_type' => 'product',
                    'content_name' => $cartItem->model->name,
                    'content_category' => $cartItem->model->productCategories->first()?->name ?? null,
                    'price' => number_format($cartItem->price, 2, '.', ''),
                ];
            }
        }


        return [
            'contents' => $items,
            'email' => self::getHashedEmail($email),
            'phone_number' => self::getHashedPhone($phoneNumber),
            'external_id' => self::getExternalId(),
            'currency' => 'EUR',
            'value' => number_format($cartTotal, 2, '.', ''),
        ];
    }

    public static function getHashedEmail(?string $email = null): string
    {
        if ($email) {
            return hash('sha256', strtolower(trim($email)));
        }

        $user = auth()->user();

        if (! $user) {
            return '';
        }

        return hash('sha256', strtolower(trim($user->email)));
    }

    public static function getHashedPhone(?string $phoneNumber = null): string
    {
        if ($phoneNumber) {
            return hash('sha256', strtolower(trim($phoneNumber)));
        }

        $user = auth()->user();

        if (! $user || ! method_exists($user::class, 'lastOrderFromAllOrders') || ! $user->lastOrderFromAllOrders || ! $user->lastOrderFromAllOrders->phone_number) {
            return '';
        }

        return hash('sha256', strtolower(trim($user->lastOrderFromAllOrders->phone_number)));
    }

    public static function getExternalId(): string
    {
        $user = auth()->user();

        if (! $user) {
            return session()->getId();
        }

        return hash('sha256', strtolower(trim($user->id)));
    }
}
