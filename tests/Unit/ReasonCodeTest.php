<?php

declare(strict_types=1);

use App\Support\Enums\ReasonCode;
use App\Support\Enums\ReasonType;

test('every ReasonCode has a valid ReasonType', function () {
    foreach (ReasonCode::cases() as $case) {
        expect($case->type())->toBeInstanceOf(ReasonType::class);
    }
});

test('every ReasonCode has non-empty Arabic and English labels', function () {
    foreach (ReasonCode::cases() as $case) {
        $ar = $case->label('ar');
        $en = $case->label('en');

        expect($ar)->toBeString()->not->toBeEmpty()
            ->and($en)->toBeString()->not->toBeEmpty();
    }
});

test('every ReasonType has non-empty Arabic and English labels', function () {
    foreach (ReasonType::cases() as $case) {
        expect($case->label('ar'))->toBeString()->not->toBeEmpty()
            ->and($case->label('en'))->toBeString()->not->toBeEmpty();
    }
});
