<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

/**
 * Externe partij (marketingbureau, BI-tool) met een eigen API-sleutel voor
 * de export-API. Bewust geen Sanctum-token: die werkt op elke auth:sanctum-
 * route, deze sleutel alleen op de routes achter AuthenticateApiClient.
 */
class ApiClient extends Model
{
    public const ABILITY_ORDERS_EXPORT = 'orders.export';

    private const TOKEN_PREFIX = 'dcx_';

    protected $table = 'dashed__api_clients';

    protected $guarded = [];

    protected $casts = [
        'abilities' => 'array',
        'is_active' => 'bool',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'token_hash',
    ];

    /** @return array<string, string> */
    public static function abilityOptions(): array
    {
        return [
            self::ABILITY_ORDERS_EXPORT => __('Orders exporteren (zonder persoonsgegevens)'),
        ];
    }

    /**
     * Maakt een nieuwe sleutel aan (de oude werkt daarna niet meer) en geeft
     * de sleutel in platte tekst terug. Alleen de hash wordt bewaard.
     */
    public function regenerateToken(): string
    {
        $token = self::TOKEN_PREFIX . Str::random(48);

        $this->forceFill([
            'token_hash' => self::hashToken($token),
            'token_prefix' => substr($token, 0, 10),
        ])->save();

        return $token;
    }

    public static function createWithToken(string $name, array $abilities): array
    {
        $client = new self([
            'name' => $name,
            'abilities' => array_values($abilities),
            'is_active' => true,
        ]);
        $token = $client->regenerateToken();

        return [$client, $token];
    }

    public static function findByToken(string $token): ?self
    {
        if (! str_starts_with($token, self::TOKEN_PREFIX)) {
            return null;
        }

        return self::where('token_hash', self::hashToken($token))->first();
    }

    public function hasAbility(string $ability): bool
    {
        return in_array($ability, (array) $this->abilities, true);
    }

    private static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
