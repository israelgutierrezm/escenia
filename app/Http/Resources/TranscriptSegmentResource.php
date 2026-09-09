<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Content\Models\TranscriptSegment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TranscriptSegment
 */
class TranscriptSegmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'start_ms' => $this->start_ms,
            'end_ms' => $this->end_ms,
            'speaker' => $this->speaker,
            'text' => $this->text,
        ];
    }
}
