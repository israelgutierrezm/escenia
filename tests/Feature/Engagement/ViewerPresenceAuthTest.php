<?php

declare(strict_types=1);
use App\Domain\Registration\Models\Attendee;

it('signs a valid presence auth for the attendee\'s own event', function () {
    [, , $event] = makeWebinarHost();
    $token = registerAttendee($event->ulid, 'Ava');

    $channel = "presence-viewers.{$event->ulid}";
    $res = $this->withHeaders(['X-Attendee-Token' => $token])
        ->postJson('/api/v1/attend/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => $channel,
        ])
        ->assertOk()
        ->assertJsonStructure(['auth', 'channel_data']);

    $channelData = (string) $res->json('channel_data');
    expect($channelData)->toContain('"role":"attendee"');

    // Pusher-protocol signature: key:hmac_sha256(socket:channel:channel_data, secret).
    /** @var array<string, mixed> $app */
    $app = (array) config('reverb.apps.apps.0');
    $expected = $app['key'].':'.hash_hmac('sha256', '123.456:'.$channel.':'.$channelData, (string) $app['secret']);
    expect($res->json('auth'))->toBe($expected);
});

it('refuses a presence auth for a different event', function () {
    [, , $eventA] = makeWebinarHost();
    [, , $eventB] = makeWebinarHost();
    $token = registerAttendee($eventA->ulid, 'Ava');

    $this->withHeaders(['X-Attendee-Token' => $token])
        ->postJson('/api/v1/attend/broadcasting/auth', [
            'socket_id' => '1.1',
            'channel_name' => "presence-viewers.{$eventB->ulid}",
        ])
        ->assertStatus(403);
});

it('requires an attendee token for presence auth', function () {
    [, , $event] = makeEventOwner();

    $this->postJson('/api/v1/attend/broadcasting/auth', [
        'socket_id' => '1.1',
        'channel_name' => "presence-viewers.{$event->ulid}",
    ])->assertUnauthorized();
});

it('signs a private auth for the attendee\'s own notification channel', function () {
    [, , $event] = makeWebinarHost();
    $token = registerAttendee($event->ulid, 'Ava', 'ava@x.test');
    $ulid = Attendee::withoutGlobalScopes()->where('email', 'ava@x.test')->firstOrFail()->ulid;

    $channel = "private-attendee.{$ulid}";
    $res = $this->withHeaders(['X-Attendee-Token' => $token])
        ->postJson('/api/v1/attend/broadcasting/auth', ['socket_id' => '1.1', 'channel_name' => $channel])
        ->assertOk()
        ->assertJsonStructure(['auth']);

    /** @var array<string, mixed> $app */
    $app = (array) config('reverb.apps.apps.0');
    $expected = $app['key'].':'.hash_hmac('sha256', '1.1:'.$channel, (string) $app['secret']);
    expect($res->json('auth'))->toBe($expected)
        ->and($res->json('channel_data'))->toBeNull();
});

it('refuses a private auth for another attendee\'s channel', function () {
    [, , $event] = makeWebinarHost();
    $token = registerAttendee($event->ulid, 'Ava', 'ava@x.test');

    $this->withHeaders(['X-Attendee-Token' => $token])
        ->postJson('/api/v1/attend/broadcasting/auth', [
            'socket_id' => '1.1',
            'channel_name' => 'private-attendee.01SOMEONEELSEULIDXXXXXXXXX',
        ])->assertStatus(403);
});
