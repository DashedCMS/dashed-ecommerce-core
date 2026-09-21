<?php

namespace Dashed\DashedEcommerceCore\Events\PriceGroups;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * syncUsers() werkt met een massa-update, dus er vuren geen modelevents op
 * de gebruikers. Wie wil weten wiens prijzen veranderden, luistert hiernaar.
 */
class PriceGroupMembersChangedEvent
{
    use Dispatchable;

    /** @param list<int> $userIds */
    public function __construct(public int $priceGroupId, public array $userIds)
    {
    }
}
