<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Wat er volgens ons bij Meta in een doelgroep staat: alleen hashes, nooit
 * het adres zelf. Nodig om bij een incrementele sync te weten wie eruit moet.
 */
class MetaAudienceMember extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'dashed__meta_audience_members';

    protected $guarded = [];
}
