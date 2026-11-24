<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Throwable;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedCore\Classes\Mails;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Contracts\Validation\Validator;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedCore\Notifications\AdminNotifier;
use Dashed\DashedEcommerceCore\Models\CheckoutAbandonment;
use Dashed\DashedEcommerceCore\Mail\AdminCheckoutErrorMail;

/**
 * Records which checkout fields failed validation (with the rejected value,
 * shortened) on the cart's CheckoutAbandonment row, and alerts the admin
 * once per cart when the failure looks unusual: anything other than a
 * forgotten required field, or the same field failing three times.
 */
class CheckoutValidationFailure
{
    /** Rules that just mean "the customer still has to fill this in". */
    public const ORDINARY_RULES = ['Required', 'RequiredIf', 'Accepted', 'AcceptedIf'];

    /** The same field failing this often means the customer is stuck. */
    public const STUCK_AFTER_ATTEMPTS = 3;

    public const ALERT_COOLDOWN_HOURS = 24;

    public const MAX_VALUE_LENGTH = 100;

    /** Never stored, whatever the field name looks like. */
    public const SECRET_FIELDS = ['password', 'passwordConfirmation'];

    /**
     * @param  array<string, mixed>  $data  The validated input.
     */
    public static function record(
        Validator $validator,
        array $data,
        ?int $cartId,
        ?string $email,
        ?float $cartTotal,
    ): ?CheckoutAbandonment {
        try {
            $errors = static::errors($validator, $data);

            $abandonment = CheckoutAbandonment::recordValidationFailure(
                errors: $errors,
                cartId: $cartId,
                email: $email,
                cartTotal: $cartTotal,
            );

            if ($abandonment && static::isUnusual($abandonment)) {
                static::alert($abandonment);
            }

            return $abandonment;
        } catch (Throwable $e) {
            // Logging may never break the checkout.
            Log::warning('Checkout validation failure could not be recorded', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, array{rules: array<int, string>, message: string, value: ?string}>
     */
    public static function errors(Validator $validator, array $data): array
    {
        $messages = $validator->errors();
        $errors = [];

        foreach ($validator->failed() as $field => $rules) {
            $errors[$field] = [
                'rules' => array_values(array_map(
                    fn (string $rule) => str_contains($rule, '\\') ? 'Custom' : $rule,
                    array_keys($rules),
                )),
                'message' => (string) $messages->first($field),
                'value' => static::storableValue($field, data_get($data, $field)),
            ];
        }

        return $errors;
    }

    public static function storableValue(string $field, mixed $value): ?string
    {
        if (in_array(Str::before($field, '.'), self::SECRET_FIELDS, true)) {
            return null;
        }

        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        if ($value === null || $value === '' || is_bool($value)) {
            return null;
        }

        return Str::limit((string) $value, self::MAX_VALUE_LENGTH);
    }

    public static function isUnusual(CheckoutAbandonment $abandonment): bool
    {
        $context = $abandonment->context ?? [];

        foreach ($context['errors'] ?? [] as $error) {
            if (array_diff($error['rules'] ?? [], self::ORDINARY_RULES) !== []) {
                return true;
            }
        }

        foreach ($context['field_failures'] ?? [] as $count) {
            if ($count >= self::STUCK_AFTER_ATTEMPTS) {
                return true;
            }
        }

        return false;
    }

    public static function alertsEnabled(): bool
    {
        return (bool) Customsetting::get('checkout_error_alert_enabled', Sites::getActive(), true);
    }

    /** @return array<int, string> */
    public static function recipients(): array
    {
        $emails = Customsetting::get('notification_checkout_error_emails', Sites::getActive(), []) ?: [];

        return array_values(array_filter((array) $emails)) ?: Mails::getAdminNotificationEmails();
    }

    protected static function alert(CheckoutAbandonment $abandonment): void
    {
        if (! static::alertsEnabled()) {
            return;
        }

        $recipients = static::recipients();
        if ($recipients === []) {
            return;
        }

        $key = 'checkout-error-alert:' . ($abandonment->cart_id ?: 'abandonment-' . $abandonment->id);
        if (! Cache::add($key, true, now()->addHours(self::ALERT_COOLDOWN_HOURS))) {
            return;
        }

        // After the response: a slow mail server must not slow down the checkout.
        $abandonmentId = $abandonment->id;
        dispatch(function () use ($abandonmentId, $recipients) {
            $abandonment = CheckoutAbandonment::find($abandonmentId);
            if ($abandonment) {
                AdminNotifier::send(new AdminCheckoutErrorMail($abandonment), $recipients);
            }
        })->afterResponse();
    }
}
