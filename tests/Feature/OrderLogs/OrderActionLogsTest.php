<?php

declare(strict_types=1);

use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Printer;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Models\PrintJob;
use Dashed\DashedEcommerceCore\Enums\PrintJobType;
use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;

beforeEach(function () {
    Mail::fake();
});

function actionLogOrder(array $attributes = []): Order
{
    return Order::create(array_merge([
        'first_name' => 'Klant',
        'last_name' => 'Jansen',
        'email' => 'klant@example.com',
        'status' => 'paid',
        'fulfillment_status' => 'unhandled',
        'street' => 'Oude straat',
        'house_nr' => '1',
        'city' => 'Utrecht',
        'total' => 100,
        'subtotal' => 100,
    ], $attributes));
}

it('geeft de afgesproken tags een Nederlandse tekst met de actor ervoor', function () {
    $order = actionLogOrder();

    OrderLog::createLog(orderId: $order->id, tag: 'order.label.created', note: 'Veloyd, T&T 3SXYZ', isSystem: true);
    OrderLog::createLog(orderId: $order->id, tag: 'order.invoice.regenerated', isSystem: true);
    OrderLog::createLog(orderId: $order->id, tag: 'order.priority.changed', note: 'Prioriteit uit', isSystem: true);

    $texts = OrderLog::where('order_id', $order->id)->orderBy('id')->get()->map->tag()->all();

    expect($texts)->toBe([
        'Systeem heeft een verzendlabel aangemaakt.',
        'Systeem heeft de factuur opnieuw gemaakt.',
        'Systeem heeft de prioriteit uitgezet.',
    ]);
});

it('zet bij een onbekende tag met notitie de actor voor de notitie', function () {
    $order = actionLogOrder();

    OrderLog::createLog(orderId: $order->id, tag: 'order.iets-nieuws', note: 'deed iets nieuws.', isSystem: true);

    expect(OrderLog::where('order_id', $order->id)->first()->tag())->toBe('Systeem deed iets nieuws.');
});

it('slaat een ingelogde printer nooit op als user_id', function () {
    $order = actionLogOrder();
    $printer = Printer::factory()->create();

    Auth::shouldReceive('user')->andReturn($printer);

    OrderLog::createLog(orderId: $order->id, tag: 'order.note.created', note: 'test');

    expect(OrderLog::where('order_id', $order->id)->first()->user_id)->toBeNull();
});

it('neemt een meegegeven userId over boven de ingelogde gebruiker', function () {
    $user = User::factory()->create(['role' => 'superadmin']);
    $other = User::factory()->create(['role' => 'superadmin']);
    $order = actionLogOrder();
    $this->actingAs($user);

    OrderLog::createLog(orderId: $order->id, tag: 'order.labelstatus.synced', userId: $other->id);
    OrderLog::createLog(orderId: $order->id, tag: 'order.invoice.regenerated');

    $logs = OrderLog::where('order_id', $order->id)->orderBy('id')->get();
    expect($logs[0]->user_id)->toBe($other->id)
        ->and($logs[1]->user_id)->toBe($user->id);
});

it('logt het klaarzetten, printen en mislukken van een printopdracht', function () {
    $user = User::factory()->create(['role' => 'superadmin']);
    $order = actionLogOrder();
    $printer = Printer::factory()->create(['name' => 'Inpaktafel']);
    $this->actingAs($user);

    $job = PrintJob::create([
        'type' => PrintJobType::PackingSlip,
        'order_id' => $order->id,
        'printer_id' => $printer->id,
        'status' => PrintJobStatus::Pending,
        'channel' => PrintJob::CHANNEL_CMS,
    ]);

    $queued = OrderLog::where('order_id', $order->id)->where('tag', 'order.print.queued')->sole();
    expect($queued->user_id)->toBe($user->id)
        ->and($queued->tag())->toBe($user->name . ' heeft de pakbon naar de printer gestuurd.')
        ->and($queued->note)->toContain('Printer: Inpaktafel')
        ->and($queued->note)->toContain('Kanaal: CMS')
        ->and($job->fresh()->user_id)->toBe($user->id);

    $job->markAsDone();
    $done = OrderLog::where('order_id', $order->id)->where('tag', 'order.print.done')->sole();
    expect($done->user_id)->toBeNull()
        ->and((bool) $done->is_system)->toBeTrue()
        ->and($done->tag())->toBe('De pakbon is geprint door Inpaktafel.');

    $job->markAsFailed('Papier op');
    $failed = OrderLog::where('order_id', $order->id)->where('tag', 'order.print.failed')->sole();
    expect($failed->tag())->toBe('Printen van de pakbon op Inpaktafel is mislukt.')
        ->and($failed->note)->toContain('Fout: Papier op');
});

it('logt een automatisch klaargezette printopdracht als systeem, ook met een ingelogde gebruiker', function () {
    $user = User::factory()->create(['role' => 'superadmin']);
    $order = actionLogOrder();
    $this->actingAs($user);

    PrintJob::create([
        'type' => PrintJobType::ShippingLabel,
        'order_id' => $order->id,
        'status' => PrintJobStatus::Pending,
        'channel' => PrintJob::CHANNEL_AUTOMATIC,
    ]);

    $log = OrderLog::where('order_id', $order->id)->where('tag', 'order.print.queued')->sole();
    expect($log->user_id)->toBeNull()
        ->and($log->tag())->toBe('Systeem heeft het verzendlabel naar de printer gestuurd.');
});

it('logt gewijzigde gegevens als "veld: oud -> nieuw" en niets zonder wijziging', function () {
    $order = actionLogOrder();

    $original = $order->getAttributes();
    $order->fill(['street' => 'Nieuwe straat', 'city' => 'Utrecht', 'company_name' => 'Lovora'])->save();

    expect(OrderLog::logDetailChanges($order, $original))->toBeTrue();
    $log = OrderLog::where('order_id', $order->id)->where('tag', 'order.details.changed')->sole();
    expect($log->note)->toBe('Bedrijfsnaam: (leeg) -> Lovora, Straat: Oude straat -> Nieuwe straat');

    expect(OrderLog::logDetailChanges($order, $order->getAttributes()))->toBeFalse();
    expect(OrderLog::where('order_id', $order->id)->where('tag', 'order.details.changed')->count())->toBe(1);
});

it('stuurt bij annuleren via changeStatus de annuleringsmail als daarom gevraagd wordt', function () {
    $order = actionLogOrder(['status' => 'pending']);

    $order->changeStatus('cancelled', true);

    Mail::assertSent(\Dashed\DashedEcommerceCore\Mail\OrderCancelledMail::class);
});

it('logt het definitief maken van een concept bij een betaallink', function () {
    $order = actionLogOrder(['status' => 'concept']);

    rescue(fn () => (new \Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Actions\SendPaymentLinkAction())
        ->handle($order, ['amount' => 10, 'email' => 'klant@example.com']), report: false);

    expect($order->fresh()->status)->toBe('pending')
        ->and(OrderLog::where('order_id', $order->id)->where('tag', 'order.concept.activated')->exists())->toBeTrue();
});
