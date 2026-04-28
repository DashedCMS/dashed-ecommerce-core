<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerMatchAccessLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'customer_match_endpoint_id',
        'slug',
        'ip',
        'user_agent',
        'status',
        'row_count',
        'failure_reason',
        'created_at',
    ];

    protected $casts = [
        'status' => 'integer',
        'row_count' => 'integer',
        'created_at' => 'datetime',
    ];

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(CustomerMatchEndpoint::class, 'customer_match_endpoint_id');
    }
}
