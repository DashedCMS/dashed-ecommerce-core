<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Notitie + vrije-tekst tags voor één klant, gekoppeld aan (site_id, email).
 * Klanten zijn in DashedCMS een aggregatie van bestellingen op e-mailadres
 * (er is geen los klant-model), dus de annotatie hangt aan het e-mailadres.
 */
class CustomerAnnotation extends Model
{
    protected $table = 'dashed__customer_annotations';

    protected $fillable = [
        'site_id',
        'email',
        'notes',
        'tags',
    ];

    protected $casts = [
        'tags' => 'array',
    ];
}
