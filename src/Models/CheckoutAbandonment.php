<?php

namespace Dashed\DashedEcommerceCore\Models;

use Dashed\DashedCore\Classes\Sites;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Legt vast waarom een klant in de checkout strandde vóórdat er een order werd
 * aangemaakt (bijv. geen verzendmethode, ongeldig btw-id, betaling starten mislukt).
 * Voedt het pre-order uitval-overzicht.
 */
class CheckoutAbandonment extends Model
{
    protected $table = 'dashed__checkout_abandonments';

    protected $fillable = [
        'site_id',
        'cart_id',
        'email',
        'reason',
        'context',
        'cart_total',
    ];

    protected $casts = [
        'context' => 'array',
        'cart_total' => 'decimal:2',
    ];

    /**
     * Aantal minuten waarbinnen dezelfde (cart, reden) niet opnieuw wordt gelogd,
     * zodat herhaald klikken op "Afrekenen" geen ruis oplevert.
     */
    public const DEDUPE_MINUTES = 30;

    /**
     * Registreer een uitval-event. Idempotent per (cart_id, reason) binnen
     * DEDUPE_MINUTES. Geeft null terug als het door dedupe is overgeslagen.
     */
    public static function record(
        string $reason,
        array $context = [],
        ?int $cartId = null,
        ?string $email = null,
        ?float $cartTotal = null,
        ?string $siteId = null,
    ): ?self {
        $siteId = $siteId ?: Sites::getActive();

        if ($cartId) {
            $recentlyLogged = static::query()
                ->where('cart_id', $cartId)
                ->where('reason', $reason)
                ->where('created_at', '>=', now()->subMinutes(self::DEDUPE_MINUTES))
                ->exists();

            if ($recentlyLogged) {
                return null;
            }
        }

        return static::create([
            'site_id' => $siteId,
            'cart_id' => $cartId,
            'email' => $email,
            'reason' => $reason,
            'context' => $context ?: null,
            'cart_total' => $cartTotal,
        ]);
    }

    /**
     * Register a failed checkout validation with the fields that failed.
     * Within DEDUPE_MINUTES the cart's existing row is updated instead:
     * attempts and per-field failure counts go up, `errors` holds the
     * latest attempt.
     *
     * @param  array<string, array{rules: array<int, string>, message: string, value: ?string}>  $errors
     */
    public static function recordValidationFailure(
        array $errors,
        ?int $cartId = null,
        ?string $email = null,
        ?float $cartTotal = null,
        ?string $siteId = null,
    ): self {
        $siteId = $siteId ?: Sites::getActive();

        $existing = $cartId
            ? static::query()
                ->where('cart_id', $cartId)
                ->where('reason', 'validation_failed')
                ->where('updated_at', '>=', now()->subMinutes(self::DEDUPE_MINUTES))
                ->latest('id')
                ->first()
            : null;

        $context = $existing?->context ?? [];
        $fieldFailures = $context['field_failures'] ?? [];
        foreach (array_keys($errors) as $field) {
            $fieldFailures[$field] = ($fieldFailures[$field] ?? 0) + 1;
        }

        $context = [
            'attempts' => ($context['attempts'] ?? 0) + 1,
            'field_failures' => $fieldFailures,
            'errors' => $errors,
        ];

        if ($existing) {
            $existing->fill([
                'email' => $email ?: $existing->email,
                'context' => $context,
                'cart_total' => $cartTotal ?? $existing->cart_total,
            ]);
            $existing->updated_at = now();
            $existing->save();

            return $existing;
        }

        return static::create([
            'site_id' => $siteId,
            'cart_id' => $cartId,
            'email' => $email,
            'reason' => 'validation_failed',
            'context' => $context,
            'cart_total' => $cartTotal,
        ]);
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function attempts(): int
    {
        return (int) ($this->context['attempts'] ?? 1);
    }

    /**
     * Readable lines like `invoiceCountry: We herkennen dit land niet (waarde: "info@…") [9x]`.
     *
     * @return array<int, string>
     */
    public function errorLines(): array
    {
        $context = $this->context ?? [];
        $lines = [];

        foreach ($context['errors'] ?? [] as $field => $error) {
            $line = $field . ': ' . ($error['message'] ?? '');
            if (filled($error['value'] ?? null)) {
                $line .= ' (waarde: "' . $error['value'] . '")';
            }
            $failures = $context['field_failures'][$field] ?? 1;
            if ($failures > 1) {
                $line .= ' [' . $failures . 'x]';
            }
            $lines[] = $line;
        }

        if ($lines === [] && filled($context)) {
            foreach ($context as $key => $value) {
                $lines[] = $key . ': ' . (is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE));
            }
        }

        return $lines;
    }
}
