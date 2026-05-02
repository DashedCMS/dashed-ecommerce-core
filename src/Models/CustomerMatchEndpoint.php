<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerMatchEndpoint extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'username',
        'password',
        'is_active',
        'customer_filter',
        'last_accessed_at',
        'last_accessed_ip',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'customer_filter' => 'array',
        'last_accessed_at' => 'datetime',
    ];

    protected $hidden = [
        'password',
    ];

    public function accessLogs(): HasMany
    {
        return $this->hasMany(CustomerMatchAccessLog::class);
    }

    public static function singleton(): self
    {
        return static::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'Google Ads Customer Match',
                'slug' => static::generateSlug(),
                'username' => static::generateUsername(),
                'password' => bcrypt(static::generatePassword()),
                'is_active' => false,
                'customer_filter' => [
                    'min_orders' => 1,
                    'since' => null,
                    'until' => null,
                    'countries' => [],
                    'tags' => [],
                ],
            ]
        );
    }

    public static function generateSlug(): string
    {
        return Str::lower(Str::random(40));
    }

    public static function generateUsername(): string
    {
        return 'google-ads-'.Str::lower(Str::random(8));
    }

    public static function generatePassword(): string
    {
        return Str::random(32);
    }

    protected function username(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => trim($value),
        );
    }

    public function recordAccess(string $ip): void
    {
        $this->forceFill([
            'last_accessed_at' => now(),
            'last_accessed_ip' => $ip,
        ])->save();
    }
}
