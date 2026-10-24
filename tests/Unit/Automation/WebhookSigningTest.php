<?php

declare(strict_types=1);

use App\Domain\Automation\Support\WebhookSigner;
use App\Domain\Automation\ValueObjects\WebhookEndpoint;
use App\Domain\Automation\ValueObjects\WebhookSecret;

it('signs exactly like the Standard Webhooks reference implementation', function () {
    // Known-answer vector from standard-webhooks libraries/php/tests/WebhookTest.php.
    $signature = WebhookSigner::sign(
        new WebhookSecret('whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw'),
        'msg_p5jXN8AQM9LWM0D4loKWxJek',
        1614265330,
        '{"test": 2432232314}',
    );

    expect($signature)->toBe('g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE=');
});

it('generates whsec_ secrets of 32 random bytes and recognises the standard format', function () {
    $secret = WebhookSecret::generate();

    expect($secret->value)->toMatch('/^whsec_[A-Za-z0-9+\/]{43}=$/')
        ->and(strlen($secret->key()))->toBe(32)
        ->and($secret->signsStandard())->toBeTrue()
        ->and(WebhookSecret::isStandard('shh'))->toBeFalse()
        ->and(WebhookSecret::isStandard('whsec_not*base64'))->toBeFalse()
        ->and(WebhookSecret::isStandard('whsec_'.base64_encode(random_bytes(16))))->toBeFalse()
        ->and(WebhookSecret::isStandard('whsec_'.base64_encode(random_bytes(65))))->toBeFalse()
        ->and(WebhookSecret::isStandard('whsec_'.base64_encode(random_bytes(24))))->toBeTrue();
});

it('signs with both secrets during a rotation grace period, the new one first', function () {
    $old = WebhookSecret::generate();
    $new = WebhookSecret::generate();
    $now = 1_700_000_000;
    $endpoint = (new WebhookEndpoint('https://hooks.example.com/x', $old))->withRotatedSecret($new, $now);
    $signer = new WebhookSigner;

    $during = $signer->headers($endpoint, 'msg_1', $now + 60, '{}');
    $after = $signer->headers($endpoint, 'msg_1', $now + WebhookEndpoint::PREVIOUS_SECRET_GRACE_SECONDS, '{}');

    expect($during['webhook-signature'])->toBe('v1,'.WebhookSigner::sign($new, 'msg_1', $now + 60, '{}').' v1,'.WebhookSigner::sign($old, 'msg_1', $now + 60, '{}'))
        ->and($after['webhook-signature'])->toBe('v1,'.WebhookSigner::sign($new, 'msg_1', $now + WebhookEndpoint::PREVIOUS_SECRET_GRACE_SECONDS, '{}'))
        ->and($endpoint->previousSecretExpiresAt($now)?->getTimestamp())->toBe($now + WebhookEndpoint::PREVIOUS_SECRET_GRACE_SECONDS)
        ->and($endpoint->previousSecretExpiresAt($now + WebhookEndpoint::PREVIOUS_SECRET_GRACE_SECONDS))->toBeNull();
});

it('keeps at most two secrets when rotating again within the grace period', function () {
    $first = WebhookSecret::generate();
    $second = WebhookSecret::generate();
    $third = WebhookSecret::generate();
    $endpoint = (new WebhookEndpoint('https://hooks.example.com/x', $first))
        ->withRotatedSecret($second, 100)
        ->withRotatedSecret($third, 200);

    expect(array_map(fn (WebhookSecret $s): string => $s->value, $endpoint->signingSecrets(300)))->toBe([$third->value, $second->value]);
});

it('treats a secret without the ADR-038 marker as legacy', function () {
    $legacy = WebhookEndpoint::fromArray(['url' => 'https://hooks.example.com/x', 'secret' => 'shh']);
    $standard = WebhookEndpoint::fromArray(['url' => 'https://hooks.example.com/x', 'secret' => WebhookSecret::generate()->value, 'legacy' => false]);
    $signer = new WebhookSigner;

    $legacyHeaders = $signer->headers($legacy ?? throw new LogicException, 'msg_1', 1, '{"a":1}');

    expect($legacyHeaders)->not->toHaveKey('webhook-signature')
        ->and($legacyHeaders['X-Escenia-Signature'])->toBe(hash_hmac('sha256', '{"a":1}', 'shh'))
        ->and($signer->headers($standard ?? throw new LogicException, 'msg_1', 1, '{}'))->not->toHaveKey('X-Escenia-Signature')
        ->and($standard->toArray())->toHaveKey('legacy', false);
});
