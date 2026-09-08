<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Media\Contracts\MediaEgressProvider;
use App\Domain\Media\Contracts\MediaProviderContract;
use App\Infrastructure\Media\FakeMediaProvider;
use App\Infrastructure\Media\LiveKit\LiveKitMediaProvider;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the media provider selected by config/media.php. Keeps LiveKit behind
 * the media contracts so the domain never depends on the SDK (ADR-002/008).
 * The concrete provider implements both the room/token and egress contracts, so
 * both resolve to the same singleton instance.
 */
class MediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MediaProviderContract::class, fn (): MediaProviderContract => $this->makeProvider());
        $this->app->alias(MediaProviderContract::class, MediaEgressProvider::class);
    }

    private function makeProvider(): MediaProviderContract
    {
        /** @var array<string, mixed> $livekit */
        $livekit = (array) config('media.livekit');

        return match (config('media.provider')) {
            'livekit' => new LiveKitMediaProvider(
                apiKey: (string) ($livekit['api_key'] ?? ''),
                apiSecret: (string) ($livekit['api_secret'] ?? ''),
                url: (string) ($livekit['url'] ?? ''),
                host: (string) ($livekit['host'] ?? ''),
                tokenTtlSeconds: (int) ($livekit['token_ttl'] ?? 3600),
            ),
            default => new FakeMediaProvider,
        };
    }
}
