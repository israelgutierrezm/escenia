<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Content\Contracts\RecordingStorage;
use App\Domain\Content\Contracts\Transcriber;
use App\Infrastructure\Storage\FakeRecordingStorage;
use App\Infrastructure\Storage\S3RecordingStorage;
use App\Infrastructure\Transcription\FakeTranscriber;
use App\Infrastructure\Transcription\HttpTranscriber;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the recording storage and transcriber selected by config/recordings.php.
 * Keeps object storage and the transcription API behind contracts so the domain
 * never depends on an SDK (ADR-026), with network-free fakes by default.
 */
class ContentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RecordingStorage::class, fn (): RecordingStorage => match (config('recordings.storage')) {
            's3' => new S3RecordingStorage((string) config('recordings.disk', 's3')),
            default => new FakeRecordingStorage,
        });

        $this->app->singleton(Transcriber::class, fn (Application $app): Transcriber => match (config('recordings.transcriber')) {
            'http' => new HttpTranscriber($app->make(RecordingStorage::class), (array) config('recordings.http')),
            default => new FakeTranscriber,
        });
    }
}
