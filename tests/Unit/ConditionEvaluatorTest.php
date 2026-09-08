<?php

declare(strict_types=1);

use App\Domain\Automation\Support\ConditionEvaluator;

beforeEach(function () {
    $this->evaluator = new ConditionEvaluator;
    $this->context = ['event.type' => 'Webinar', 'order.total_minor' => 5000, 'contact.email' => 'a@b.com'];
});

it('passes with no conditions', function () {
    expect($this->evaluator->passes(null, $this->context))->toBeTrue()
        ->and($this->evaluator->passes([], $this->context))->toBeTrue();
});

it('evaluates equality case-insensitively and numeric comparisons', function () {
    expect($this->evaluator->passes([['field' => 'event.type', 'op' => 'eq', 'value' => 'webinar']], $this->context))->toBeTrue()
        ->and($this->evaluator->passes([['field' => 'order.total_minor', 'op' => 'gte', 'value' => 5000]], $this->context))->toBeTrue()
        ->and($this->evaluator->passes([['field' => 'order.total_minor', 'op' => 'gt', 'value' => 5000]], $this->context))->toBeFalse()
        ->and($this->evaluator->passes([['field' => 'contact.email', 'op' => 'contains', 'value' => '@b.com']], $this->context))->toBeTrue();
});

it('ANDs multiple conditions and fails closed on malformed', function () {
    expect($this->evaluator->passes([
        ['field' => 'event.type', 'op' => 'eq', 'value' => 'webinar'],
        ['field' => 'order.total_minor', 'op' => 'lt', 'value' => 1000],
    ], $this->context))->toBeFalse()
        ->and($this->evaluator->passes([['field' => '', 'op' => 'bogus']], $this->context))->toBeFalse()
        ->and($this->evaluator->passes(['not-an-object'], $this->context))->toBeFalse();
});
