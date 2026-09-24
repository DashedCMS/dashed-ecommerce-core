<?php

use Livewire\Livewire;
use Filament\Facades\Filament;
use Dashed\DashedCore\Models\User;
use Dashed\DashedPages\Models\Page;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Models\OrderReturnLine;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderReturnResource\Pages\ViewOrderReturn;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderReturnResource\Pages\ListOrderReturns;

beforeEach(function () {
    Mail::fake();
    Queue::fake();
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    Filament::setCurrentPanel(Filament::getPanel('dashed'));

    // ViewOrder bouwt een knop met Order::getUrl(), en die valt over een
    // ontbrekende besteloverzicht-pagina heen (zie ModifyOrderActionGroupTest).
    $page = Page::create([
        'name' => ['nl' => 'Bestelling', 'en' => 'Order'],
        'slug' => ['nl' => 'bestelling', 'en' => 'order'],
        'site_ids' => [Sites::getActive()],
        'public' => 1,
    ]);
    Customsetting::set('order_page_id', $page->id);
});

function filamentReturn(string $status = OrderReturn::STATUS_APPROVED): array
{
    $f = spilOrder();
    $return = OrderReturn::create(['order_id' => $f['order']->id, 'email' => 'klant@example.com', 'status' => $status, 'approved_at' => now()]);
    $line = OrderReturnLine::create(['order_return_id' => $return->id, 'order_product_id' => $f['shirt']->id, 'quantity' => 2]);

    return $f + ['return' => $return->fresh(), 'line' => $line];
}

it('toont verwerken, sluiten en afkeuren bij goedgekeurd en geen afgehandeld-knop meer', function () {
    $f = filamentReturn();

    Livewire::test(ViewOrderReturn::class, ['record' => $f['return']->id])
        ->assertActionVisible('process')
        ->assertActionVisible('close')
        ->assertActionVisible('reject')
        ->assertActionHidden('approve')
        ->assertActionHidden('registerRefund')
        ->assertActionDoesNotExist('markHandled');
});

it('verwerkt via de modal en koppelt de creditorder', function () {
    $f = filamentReturn();

    Livewire::test(ViewOrderReturn::class, ['record' => $f['return']->id])
        ->callAction('process', data: [
            'lines' => [$f['line']->id => 2],
            'restock' => false,
            'refund_discount' => false,
            'note' => 'Alles in orde',
        ])
        ->assertHasNoActionErrors();

    $return = $f['return']->fresh();
    expect($return->status)->toBe(OrderReturn::STATUS_HANDLED)
        ->and($return->credit_order_id)->not->toBeNull()
        ->and(Order::find($return->credit_order_id)->credit_for_order_id)->toBe($f['order']->id);
});

it('vraagt een extra bevestiging als de bestelling al een creditorder heeft', function () {
    $f = filamentReturn();
    app(\Dashed\DashedEcommerceCore\Services\OrderReturn\ReturnProcessor::class)
        ->process($f['return'], [['order_return_line_id' => $f['line']->id, 'quantity' => 1]], ['restock' => false]);

    $tweede = OrderReturn::create(['order_id' => $f['order']->id, 'email' => 'klant@example.com', 'status' => OrderReturn::STATUS_APPROVED, 'approved_at' => now()]);
    $lijn = OrderReturnLine::create(['order_return_id' => $tweede->id, 'order_product_id' => $f['shirt']->id, 'quantity' => 1]);

    Livewire::test(ViewOrderReturn::class, ['record' => $tweede->id])
        ->mountAction('process')
        ->setActionData(['lines' => [$lijn->id => 1], 'restock' => false, 'confirm_existing_credit' => false])
        ->callMountedAction()
        ->assertHasActionErrors(['confirm_existing_credit' => 'accepted']);

    expect($tweede->fresh()->status)->toBe(OrderReturn::STATUS_APPROVED)
        ->and(Order::where('credit_for_order_id', $f['order']->id)->count())->toBe(1);

    Livewire::test(ViewOrderReturn::class, ['record' => $tweede->id])
        ->callAction('process', data: ['lines' => [$lijn->id => 1], 'restock' => false, 'confirm_existing_credit' => true])
        ->assertHasNoActionErrors();

    expect($tweede->fresh()->status)->toBe(OrderReturn::STATUS_HANDLED)
        ->and(Order::where('credit_for_order_id', $f['order']->id)->count())->toBe(2);
});

it('sluit via de modal met reden', function () {
    $f = filamentReturn();

    Livewire::test(ViewOrderReturn::class, ['record' => $f['return']->id])
        ->callAction('close', data: ['closed_reason' => 'Niets ontvangen'])
        ->assertHasNoActionErrors();

    expect($f['return']->fresh()->status)->toBe(OrderReturn::STATUS_CLOSED);
});

it('toont terugbetaling registreren alleen bij verwerkt zonder betaling en boekt hem', function () {
    $f = filamentReturn(OrderReturn::STATUS_HANDLED);
    $credit = Order::create(['email' => 'klant@example.com', 'status' => 'return', 'credit_for_order_id' => $f['order']->id, 'total' => -40, 'invoice_id' => 'CR-F']);
    $f['return']->update(['credit_order_id' => $credit->id]);

    $page = Livewire::test(ViewOrderReturn::class, ['record' => $f['return']->id])
        ->assertActionVisible('registerRefund')
        ->assertActionHidden('process')
        ->callAction('registerRefund', data: ['amount' => 40, 'method' => 'Bankoverschrijving'])
        ->assertHasNoActionErrors();

    expect($f['return']->fresh()->isRefunded())->toBeTrue();

    Livewire::test(ViewOrderReturn::class, ['record' => $f['return']->id])
        ->assertActionHidden('registerRefund');
});

it('toont in de lijst de creditorder en het gecrediteerde bedrag', function () {
    $f = filamentReturn(OrderReturn::STATUS_HANDLED);
    $credit = Order::create(['email' => 'klant@example.com', 'status' => 'return', 'credit_for_order_id' => $f['order']->id, 'total' => -40, 'invoice_id' => 'CR-LIJST']);
    $f['return']->update(['credit_order_id' => $credit->id]);

    Livewire::test(ListOrderReturns::class)
        ->assertCanSeeTableRecords([$f['return']])
        ->assertSee('CR-LIJST')
        ->assertSee('40');
});

it('meldt vanuit de bestelling een retour aan als goedgekeurd', function () {
    $f = spilOrder();

    Livewire::test(\Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Pages\ViewOrder::class, ['record' => $f['order']->id])
        ->assertActionVisible('registerReturn')
        ->callAction('registerReturn', data: [
            'lines' => [$f['shirt']->id => ['quantity' => 2, 'return_reason_id' => null, 'reason_note' => 'Te klein']],
            'notify_customer' => false,
            'admin_note' => 'Gebeld',
        ])
        ->assertHasNoActionErrors();

    $return = OrderReturn::where('order_id', $f['order']->id)->first();
    expect($return)->not->toBeNull()
        ->and($return->status)->toBe(OrderReturn::STATUS_APPROVED)
        ->and($return->lines->first()->quantity)->toBe(2);
});

it('schakelt retour aanmelden uit bij een open retour en bij een onbetaalde bestelling, en verbergt hem op een creditorder', function () {
    $f = filamentReturn();
    Livewire::test(\Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Pages\ViewOrder::class, ['record' => $f['order']->id])
        ->assertActionVisible('registerReturn')
        ->assertActionDisabled('registerReturn');

    $g = spilOrder(['status' => 'pending']);
    Livewire::test(\Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Pages\ViewOrder::class, ['record' => $g['order']->id])
        ->assertActionDisabled('registerReturn');

    $credit = Order::create(['email' => 'a@b.nl', 'status' => 'return', 'credit_for_order_id' => $f['order']->id, 'total' => -10, 'invoice_id' => 'CR-HIDE']);
    Livewire::test(\Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Pages\ViewOrder::class, ['record' => $credit->id])
        ->assertActionHidden('registerReturn');
});

it('heeft een knop nieuwe retour boven de lijst', function () {
    Livewire::test(ListOrderReturns::class)->assertActionExists('registerReturn');
});

it('toont op de creditorder uit welke retour hij komt', function () {
    $f = filamentReturn(OrderReturn::STATUS_HANDLED);
    $credit = Order::create(['email' => 'klant@example.com', 'status' => 'return', 'credit_for_order_id' => $f['order']->id, 'total' => -40, 'invoice_id' => 'CR-ORIG', 'hash' => 'h']);
    $f['return']->update(['credit_order_id' => $credit->id]);

    Livewire::test(\Dashed\DashedEcommerceCore\Livewire\Orders\Infolists\ViewStatusses::class, ['order' => $credit])
        ->assertSee('Ontstaan uit retour #' . $f['return']->id);
});
