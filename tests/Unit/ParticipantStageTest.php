<?php

declare(strict_types=1);

use App\Domain\Studio\Enums\ParticipantStage;

it('allows only the declared stage transitions', function () {
    expect(ParticipantStage::Invited->canTransitionTo(ParticipantStage::GreenRoom))->toBeTrue()
        ->and(ParticipantStage::GreenRoom->canTransitionTo(ParticipantStage::Stage))->toBeFalse()
        ->and(ParticipantStage::GreenRoom->canTransitionTo(ParticipantStage::Backstage))->toBeTrue()
        ->and(ParticipantStage::Backstage->canTransitionTo(ParticipantStage::Stage))->toBeTrue()
        ->and(ParticipantStage::Stage->canTransitionTo(ParticipantStage::Backstage))->toBeTrue()
        ->and(ParticipantStage::Stage->canTransitionTo(ParticipantStage::Left))->toBeTrue()
        ->and(ParticipantStage::Left->canTransitionTo(ParticipantStage::Stage))->toBeFalse();
});

it('knows which stages hold a room seat', function () {
    expect(ParticipantStage::GreenRoom->isInRoom())->toBeTrue()
        ->and(ParticipantStage::Backstage->isInRoom())->toBeTrue()
        ->and(ParticipantStage::Stage->isInRoom())->toBeTrue()
        ->and(ParticipantStage::Invited->isInRoom())->toBeFalse()
        ->and(ParticipantStage::Left->isInRoom())->toBeFalse();
});
