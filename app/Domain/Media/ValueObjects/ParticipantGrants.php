<?php

declare(strict_types=1);

namespace App\Domain\Media\ValueObjects;

/**
 * The media capabilities granted to a participant when joining a room. Pure
 * capabilities — the domain decides these from role/stage and passes them here.
 */
final class ParticipantGrants
{
    public function __construct(
        public readonly bool $canPublish,
        public readonly bool $canSubscribe,
        public readonly bool $canPublishData,
    ) {}

    public static function subscribeOnly(): self
    {
        return new self(canPublish: false, canSubscribe: true, canPublishData: false);
    }

    public static function full(): self
    {
        return new self(canPublish: true, canSubscribe: true, canPublishData: true);
    }
}
