<?php

use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Http;
use Dashed\DashedMobileApi\Models\DeviceToken;
use Dashed\DashedMobileApi\Support\AbilityResolver;
use Dashed\DashedMobileApi\Support\ExpoPushService;

/**
 * Fix: na uitloggen bij een site blijven pushes komen. De device-rij wordt nu
 * aan de Sanctum-sessie gekoppeld; uitloggen ruimt 'm op, er is een deregister-
 * endpoint, en de dispatch slaat een ingetrokken sessie over (self-healing +
 * retroactief zodra sessieloze legacy-rijen zijn opgeschoond).
 */
it('dispatch slaat een uitgelogde (ingetrokken) sessie over, maar houdt legacy + geldige', function () {
    Http::fake(['exp.host/*' => Http::response(['data' => []], 200)]);
    $this->mock(AbilityResolver::class, fn ($m) => $m->shouldReceive('abilitiesFor')->andReturn(['push.test']));

    $user = User::factory()->create();
    $liveId = $user->createToken('t')->accessToken->id;

    DeviceToken::create(['user_id' => $user->id, 'token' => 'ExponentPushToken[legacy]', 'platform' => 'ios', 'access_token_id' => null]);
    DeviceToken::create(['user_id' => $user->id, 'token' => 'ExponentPushToken[live]', 'platform' => 'ios', 'access_token_id' => $liveId]);
    DeviceToken::create(['user_id' => $user->id, 'token' => 'ExponentPushToken[revoked]', 'platform' => 'ios', 'access_token_id' => 999999]);

    app(ExpoPushService::class)->notifyAbility('push.test', 'Titel', 'Body');

    $tos = [];
    Http::assertSent(function ($request) use (&$tos): bool {
        if (! str_contains($request->url(), 'exp.host')) {
            return false;
        }
        foreach ($request->data() as $message) {
            $tos[] = $message['to'];
        }

        return true;
    });

    expect($tos)->toContain('ExponentPushToken[legacy]')
        ->toContain('ExponentPushToken[live]')
        ->not->toContain('ExponentPushToken[revoked]');
});

it('registratie koppelt het token aan de huidige sessie', function () {
    $user = User::factory()->create();
    $created = $user->createToken('t', ['*']);

    $this->withToken($created->plainTextToken)
        ->postJson('/api/v1/devices', ['token' => 'ExponentPushToken[reg]', 'platform' => 'ios'], ['X-Site-Id' => 'default'])
        ->assertCreated();

    expect((int) DeviceToken::where('token', 'ExponentPushToken[reg]')->value('access_token_id'))
        ->toBe((int) $created->accessToken->id);
});

it('DELETE /devices deregistreert het token van de gebruiker', function () {
    $user = User::factory()->create();
    $plain = $user->createToken('t', ['*'])->plainTextToken;
    DeviceToken::create(['user_id' => $user->id, 'token' => 'ExponentPushToken[abc]', 'platform' => 'ios']);

    $this->withToken($plain)
        ->deleteJson('/api/v1/devices', ['token' => 'ExponentPushToken[abc]'], ['X-Site-Id' => 'default'])
        ->assertOk()
        ->assertJsonPath('deleted', 1);

    expect(DeviceToken::where('token', 'ExponentPushToken[abc]')->exists())->toBeFalse();
});

it('logout ruimt het aan de sessie gekoppelde device-token op', function () {
    $user = User::factory()->create();
    $created = $user->createToken('t', ['*']);
    DeviceToken::create(['user_id' => $user->id, 'token' => 'ExponentPushToken[xyz]', 'platform' => 'ios', 'access_token_id' => $created->accessToken->id]);

    $this->withToken($created->plainTextToken)
        ->postJson('/api/v1/auth/logout', [], ['X-Site-Id' => 'default'])
        ->assertOk();

    expect(DeviceToken::where('token', 'ExponentPushToken[xyz]')->exists())->toBeFalse();
});
