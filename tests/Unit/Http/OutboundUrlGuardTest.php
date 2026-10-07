<?php

declare(strict_types=1);

use App\Infrastructure\Http\HostResolver;
use App\Infrastructure\Http\OutboundUrlGuard;
use App\Infrastructure\Http\UnsafeOutboundUrlException;

/**
 * @param  list<string>  $addresses
 */
function guardResolvingTo(array $addresses, bool $allowPrivate = false): OutboundUrlGuard
{
    return new OutboundUrlGuard(new class($addresses) implements HostResolver
    {
        /**
         * @param  list<string>  $addresses
         */
        public function __construct(private readonly array $addresses) {}

        public function resolve(string $host): array
        {
            return $this->addresses;
        }
    }, $allowPrivate);
}

it('pins a request to the public address it checked and never follows redirects', function () {
    $options = guardResolvingTo(['93.184.216.34'])->pinnedOptions('https://idp.example.com/token');

    expect($options['allow_redirects'])->toBeFalse()
        ->and($options['curl'][CURLOPT_RESOLVE])->toBe(['idp.example.com:443:93.184.216.34']);
});

it('pins IPv6 addresses and explicit ports', function () {
    $options = guardResolvingTo(['2606:2800:220:1:248:1893:25c8:1946'])->pinnedOptions('http://idp.example.com:8443/jwks');

    expect($options['curl'][CURLOPT_RESOLVE])->toBe(['idp.example.com:8443:[2606:2800:220:1:248:1893:25c8:1946]']);
});

it('refuses hosts that resolve to non-public addresses', function (string $address) {
    guardResolvingTo([$address])->pinnedOptions('https://idp.example.com/token');
})->throws(UnsafeOutboundUrlException::class)->with([
    'cloud metadata' => '169.254.169.254',
    'loopback' => '127.0.0.1',
    'private 10/8' => '10.1.2.3',
    'private 172.16/12' => '172.20.0.1',
    'private 192.168/16' => '192.168.1.10',
    'CGNAT' => '100.64.0.1',
    'IPv6 loopback' => '::1',
    'IPv6 unique local' => 'fd00::1',
    'IPv6 link-local' => 'fe80::1',
    'IPv4-mapped IPv6' => '::ffff:10.0.0.1',
]);

it('refuses a host when any of its addresses is private', function () {
    guardResolvingTo(['93.184.216.34', '10.0.0.1'])->pinnedOptions('https://idp.example.com/token');
})->throws(UnsafeOutboundUrlException::class);

it('refuses IP-literal URLs to private ranges', function () {
    guardResolvingTo([])->pinnedOptions('http://169.254.169.254/latest/meta-data/');
})->throws(UnsafeOutboundUrlException::class);

it('refuses hosts that do not resolve and non-http schemes', function (string $url) {
    guardResolvingTo([])->pinnedOptions($url);
})->throws(UnsafeOutboundUrlException::class)->with([
    'unresolvable' => 'https://nowhere.invalid/token',
    'file scheme' => 'file:///etc/passwd',
    'gopher scheme' => 'gopher://idp.example.com/',
]);

it('allows private networks only when configured for local development', function () {
    $options = guardResolvingTo(['127.0.0.1'], allowPrivate: true)->pinnedOptions('http://localhost:8080/realms/dev');

    expect($options['curl'][CURLOPT_RESOLVE])->toBe(['localhost:8080:127.0.0.1']);
});
