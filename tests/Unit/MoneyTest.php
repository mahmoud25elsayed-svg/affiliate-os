<?php

declare(strict_types=1);

use App\Support\Casts\MoneyCast;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

test('money can be instantiated from minor units', function () {
    $money = new Money(1500, 'EGP');

    expect($money->amount)->toBe(1500)
        ->and($money->currency)->toBe('EGP')
        ->and($money->toDecimalString())->toBe('15.00')
        ->and($money->format('ar'))->toBe('15.00 ج.م')
        ->and($money->format('en'))->toBe('EGP 15.00');
});

test('fromDecimalString parses decimals strictly without floats', function () {
    expect(Money::fromDecimalString('12.5')->amount)->toBe(1250)
        ->and(Money::fromDecimalString('12.50')->amount)->toBe(1250)
        ->and(Money::fromDecimalString('100')->amount)->toBe(10000)
        ->and(Money::fromDecimalString('0.75')->amount)->toBe(75)
        ->and(Money::fromDecimalString('-5.20')->amount)->toBe(-520);
});

test('fromDecimalString throws on invalid string', function () {
    Money::fromDecimalString('abc');
})->throws(InvalidArgumentException::class);

test('passing float throws exception explicitly', function () {
    Money::fromFloat(12.5);
})->throws(InvalidArgumentException::class);

test('money addition and subtraction work correctly', function () {
    $m1 = Money::fromMinor(1000);
    $m2 = Money::fromMinor(500);

    expect($m1->add($m2)->amount)->toBe(1500)
        ->and($m1->subtract($m2)->amount)->toBe(500);
});

test('money multiplication rejects floats and accepts integers', function () {
    $m = Money::fromMinor(250);

    expect($m->multiply(3)->amount)->toBe(750);
});

test('currency mismatch throws exception on arithmetic', function () {
    $egp = new Money(1000, 'EGP');
    $usd = new Money(1000, 'USD');

    $egp->add($usd);
})->throws(InvalidArgumentException::class);

test('allocate distributes remainder without penny loss', function () {
    // Exact requirement: allocate(100, [1, 1, 1]) = [34, 33, 33]
    $m = Money::fromMinor(100);
    $shares = $m->allocate([1, 1, 1]);

    expect($shares)->toHaveCount(3)
        ->and($shares[0]->amount)->toBe(34)
        ->and($shares[1]->amount)->toBe(33)
        ->and($shares[2]->amount)->toBe(33);

    // Sum matches exactly
    $total = array_sum(array_map(fn ($s) => $s->amount, $shares));
    expect($total)->toBe(100);
});

test('allocate handles asymmetric ratios', function () {
    $m = Money::fromMinor(100);
    $shares = $m->allocate([1, 2, 1]);

    expect($shares[0]->amount)->toBe(25)
        ->and($shares[1]->amount)->toBe(50)
        ->and($shares[2]->amount)->toBe(25);
});

test('comparisons work as expected', function () {
    $m1 = Money::fromMinor(1000);
    $m2 = Money::fromMinor(1000);
    $m3 = Money::fromMinor(500);

    expect($m1->equals($m2))->toBeTrue()
        ->and($m1->equals($m3))->toBeFalse()
        ->and($m1->greaterThan($m3))->toBeTrue()
        ->and($m3->lessThan($m1))->toBeTrue()
        ->and($m1->isPositive())->toBeTrue()
        ->and(Money::fromMinor(0)->isZero())->toBeTrue()
        ->and(Money::fromMinor(-100)->isNegative())->toBeTrue();
});

test('MoneyCast gets and sets integer amounts', function () {
    $cast = new MoneyCast;
    $model = new class extends Model {};

    // Get
    $money = $cast->get($model, 'price', 2500, ['currency_code' => 'EGP']);
    expect($money)->toBeInstanceOf(Money::class)
        ->and($money->amount)->toBe(2500);

    // Set from Money
    expect($cast->set($model, 'price', $money, []))->toBe(2500);

    // Set from decimal string
    expect($cast->set($model, 'price', '15.50', []))->toBe(1550);

    // Set from float throws
    expect(fn () => $cast->set($model, 'price', 15.50, []))->toThrow(InvalidArgumentException::class);
});
