<?php

use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\PriceGroup;

it('attaches selected users, reassigns from another group, and detaches removed users', function () {
    $group = PriceGroup::create(['name' => 'Doelgroep']);
    $otherGroup = PriceGroup::create(['name' => 'Andere groep']);

    $fresh = User::factory()->create();
    $fromOther = User::factory()->create(['price_group_id' => $otherGroup->id]);
    $existingMember = User::factory()->create(['price_group_id' => $group->id]);

    $group->syncUsers([$fresh->id, $fromOther->id]);

    expect($fresh->fresh()->price_group_id)->toBe($group->id)
        ->and($fromOther->fresh()->price_group_id)->toBe($group->id)
        ->and($existingMember->fresh()->price_group_id)->toBeNull();
});

it('detaches all members when given an empty selection', function () {
    $group = PriceGroup::create(['name' => 'Doelgroep']);
    $member = User::factory()->create(['price_group_id' => $group->id]);

    $group->syncUsers([]);

    expect($member->fresh()->price_group_id)->toBeNull();
});
