<?php

declare(strict_types=1);

use App\Domain\Studio\Enums\ParticipantRole;
use App\Domain\Studio\Enums\ParticipantStage;
use App\Domain\Studio\ParticipantGrantPolicy;

it('grants full media control to hosts in the room', function () {
    $grants = ParticipantGrantPolicy::for(ParticipantRole::Host, ParticipantStage::GreenRoom);

    expect($grants->canPublish)->toBeTrue()
        ->and($grants->canSubscribe)->toBeTrue()
        ->and($grants->canPublishData)->toBeTrue();
});

it('limits guests in the green room (no data channel yet)', function () {
    $grants = ParticipantGrantPolicy::for(ParticipantRole::Guest, ParticipantStage::GreenRoom);

    expect($grants->canPublish)->toBeTrue()
        ->and($grants->canSubscribe)->toBeTrue()
        ->and($grants->canPublishData)->toBeFalse();
});

it('opens the data channel for guests on stage', function () {
    $grants = ParticipantGrantPolicy::for(ParticipantRole::Guest, ParticipantStage::Stage);

    expect($grants->canPublishData)->toBeTrue();
});

it('grants nothing to a participant outside the room', function () {
    $grants = ParticipantGrantPolicy::for(ParticipantRole::Guest, ParticipantStage::Left);

    expect($grants->canPublish)->toBeFalse()
        ->and($grants->canSubscribe)->toBeFalse()
        ->and($grants->canPublishData)->toBeFalse();
});
