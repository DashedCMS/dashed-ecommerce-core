<?php

namespace Dashed\DashedEcommerceCore\Support\Orders;

use Illuminate\Database\Eloquent\Model;

/**
 * Een medewerker in een API-payload: altijd {id, name, avatar_url}. Gebruikt
 * User::toAvatarPayload() van dashed-core als die bestaat.
 */
class UserPayload
{
    /** @return array{id: int, name: ?string, avatar_url: ?string} */
    public static function for(Model $user): array
    {
        if (method_exists($user, 'toAvatarPayload')) {
            $payload = (array) $user->toAvatarPayload();

            return array_merge(['id' => (int) $user->getKey(), 'name' => $user->name, 'avatar_url' => null], $payload);
        }

        return [
            'id' => (int) $user->getKey(),
            'name' => $user->name,
            'avatar_url' => rescue(fn () => $user->avatar_url, null, false),
        ];
    }

    /** Zelfde vorm, of alleen id als de gebruiker niet (meer) bestaat; null zonder id. */
    public static function forId(?int $userId, ?Model $user): ?array
    {
        if (! $userId) {
            return null;
        }

        return $user ? self::for($user) : ['id' => $userId, 'name' => null, 'avatar_url' => null];
    }
}
