<?php

declare(strict_types=1);

namespace App\Infrastructure\Transcription;

use App\Domain\Content\Contracts\RecordingStorage;
use App\Domain\Content\Contracts\Transcriber;
use App\Domain\Content\Models\Recording;
use App\Domain\Content\ValueObjects\TranscriptResult;
use App\Domain\Content\ValueObjects\TranscriptSegmentData;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * HTTP transcription adapter (e.g. Whisper/AssemblyAI-style). Submits the
 * recording's playback URL and maps the provider's segments back to VOs. Not
 * integration-tested (see technical-debt); the fake transcriber is the default.
 * The SDK/API never enters the domain.
 */
final class HttpTranscriber implements Transcriber
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly RecordingStorage $storage,
        private readonly array $config,
    ) {}

    public function transcribe(Recording $recording, string $language): TranscriptResult
    {
        $endpoint = (string) ($this->config['endpoint'] ?? '');
        $apiKey = (string) ($this->config['api_key'] ?? '');

        if ($endpoint === '' || $recording->disk === null || $recording->storage_key === null) {
            throw new RuntimeException('HTTP transcriber is not configured or the recording has no asset.');
        }

        $response = Http::withToken($apiKey)
            ->timeout(120)
            ->post($endpoint, [
                'audio_url' => $this->storage->playbackUrl($recording->disk, $recording->storage_key),
                'language' => $language,
            ])
            ->throw();

        $segments = [];
        /** @var array<int, array<string, mixed>> $rows */
        $rows = is_array($response->json('segments')) ? $response->json('segments') : [];

        foreach ($rows as $row) {
            $segments[] = new TranscriptSegmentData(
                startMs: (int) ($row['start_ms'] ?? 0),
                endMs: (int) ($row['end_ms'] ?? 0),
                speaker: isset($row['speaker']) ? (string) $row['speaker'] : null,
                text: (string) ($row['text'] ?? ''),
            );
        }

        return new TranscriptResult($language, $segments);
    }

    public function name(): string
    {
        return 'http';
    }
}
