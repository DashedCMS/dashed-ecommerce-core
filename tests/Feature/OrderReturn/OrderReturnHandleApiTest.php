<?php

use Laravel\Sanctum\Sanctum;
use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Models\OrderReturnLine;

beforeEach(function () {
    Mail::fake();
    Queue::fake();
    registerMobileSites();
    $user = User::create(['first_name' => 'A', 'email' => 'api-' . uniqid() . '@x.nl', 'password' => bcrypt('x'), 'role' => 'admin']);
    Sanctum::actingAs($user, ['orders.read', 'orders.write']);
});

function handleApiReturn(): array
{
    $f = spilOrder(['site_id' => \Dashed\DashedCore\Classes\Sites::getActive()]);
    $return = OrderReturn::create(['site_id' => $f['order']->site_id, 'order_id' => $f['order']->id, 'email' => 'klant@example.com', 'status' => OrderReturn::STATUS_APPROVED]);
    $line = OrderReturnLine::create(['order_return_id' => $return->id, 'order_product_id' => $f['shirt']->id, 'quantity' => 2]);

    return $f + ['return' => $return, 'line' => $line];
}

it('verwerkt via de app met alle aangemelde aantallen en geeft de creditorder terug', function () {
    $f = handleApiReturn();

    $this->postJson("/api/v1/returns/{$f['return']->id}/handle", ['restock' => false])
        ->assertOk()
        ->assertJsonPath('data.status', OrderReturn::STATUS_HANDLED)
        ->assertJsonPath('data.lines.0.processed_quantity', 2);

    $return = $f['return']->fresh();
    expect($return->credit_order_id)->not->toBeNull()
        ->and(round($return->creditedAmount(), 2))->toBe(40.0);
});

it('verwerkt via de app met opgegeven regels', function () {
    $f = handleApiReturn();

    $this->postJson("/api/v1/returns/{$f['return']->id}/handle", [
        'restock' => false,
        'lines' => [['order_return_line_id' => $f['line']->id, 'quantity' => 1]],
    ])->assertOk();

    expect(round($f['return']->fresh()->creditedAmount(), 2))->toBe(20.0);
});

it('geeft 422 bij een niet-goedgekeurde retour en bij een te hoog aantal', function () {
    $f = handleApiReturn();
    $f['return']->update(['status' => OrderReturn::STATUS_REQUESTED]);
    $this->postJson("/api/v1/returns/{$f['return']->id}/handle", [])->assertStatus(422);

    $f['return']->update(['status' => OrderReturn::STATUS_APPROVED]);
    $this->postJson("/api/v1/returns/{$f['return']->id}/handle", ['lines' => [['order_return_line_id' => $f['line']->id, 'quantity' => 9]]])->assertStatus(422);
    expect($f['return']->fresh()->credit_order_id)->toBeNull();
});

it('geeft 409 met de bestaande creditorders en verwerkt pas na bevestiging', function () {
    $f = handleApiReturn();
    $this->postJson("/api/v1/returns/{$f['return']->id}/handle", ['restock' => false, 'lines' => [['order_return_line_id' => $f['line']->id, 'quantity' => 1]]])->assertOk();
    $eerste = $f['return']->fresh()->credit_order_id;

    $tweede = OrderReturn::create(['site_id' => $f['order']->site_id, 'order_id' => $f['order']->id, 'email' => 'klant@example.com', 'status' => OrderReturn::STATUS_APPROVED]);
    $lijn = OrderReturnLine::create(['order_return_id' => $tweede->id, 'order_product_id' => $f['shirt']->id, 'quantity' => 1]);

    $this->postJson("/api/v1/returns/{$tweede->id}/handle", ['restock' => false])
        ->assertStatus(409)
        ->assertJsonPath('code', 'existing_credit_order')
        ->assertJsonPath('credit_orders.0.id', $eerste);
    expect($tweede->fresh()->credit_order_id)->toBeNull();

    $this->postJson("/api/v1/returns/{$tweede->id}/handle", ['restock' => false, 'confirm_existing_credit' => true])
        ->assertOk()
        ->assertJsonPath('data.status', OrderReturn::STATUS_HANDLED);
});
