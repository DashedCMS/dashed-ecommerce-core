<?php

namespace Dashed\DashedEcommerceCore\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Browser autofill sometimes puts an e-mail address (or a postcode) in the
 * country field of the account page. Stored on the user, that value was
 * prefilled in the checkout and blocked every order. A country never
 * contains "@" or digits, so such values are cleared before saving.
 */
class UserCountryGuard
{
    public const FIELDS = ['country', 'invoice_country'];

    public static function boot(): void
    {
        $model = config('auth.providers.users.model');

        if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
            return;
        }

        $model::saving(fn (Model $user) => static::clean($user));
    }

    public static function clean(Model $user): void
    {
        foreach (self::FIELDS as $field) {
            if (! $user->isDirty($field)) {
                continue;
            }

            $value = $user->getAttribute($field);

            if (is_string($value) && ! static::looksLikeCountry($value)) {
                $user->setAttribute($field, null);
            }
        }
    }

    public static function looksLikeCountry(string $value): bool
    {
        return ! str_contains($value, '@') && ! preg_match('/\d/', $value);
    }
}
