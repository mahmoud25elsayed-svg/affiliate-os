<?php

declare(strict_types=1);

use App\Support\Phone\EgyptianPhone;

dataset('phone_cases', [
    // 1. National formats (4 networks)
    ['01012345678', true, '+201012345678', '01012345678', 'Vodafone'],
    ['01112345678', true, '+201112345678', '01112345678', 'Etisalat'],
    ['01212345678', true, '+201212345678', '01212345678', 'Orange'],
    ['01512345678', true, '+201512345678', '01512345678', 'WE'],

    // 2. International with +
    ['+201012345678', true, '+201012345678', '01012345678', 'Vodafone'],
    ['+201112345678', true, '+201112345678', '01112345678', 'Etisalat'],
    ['+201212345678', true, '+201212345678', '01212345678', 'Orange'],
    ['+201512345678', true, '+201512345678', '01512345678', 'WE'],

    // 3. International with 00
    ['00201012345678', true, '+201012345678', '01012345678', 'Vodafone'],
    ['00201112345678', true, '+201112345678', '01112345678', 'Etisalat'],
    ['00201212345678', true, '+201212345678', '01212345678', 'Orange'],
    ['00201512345678', true, '+201512345678', '01512345678', 'WE'],

    // 4. Country code without + (201x)
    ['201012345678', true, '+201012345678', '01012345678', 'Vodafone'],
    ['201112345678', true, '+201112345678', '01112345678', 'Etisalat'],

    // 5. Without country code or leading 0 (10x, 11x, 12x, 15x)
    ['1012345678', true, '+201012345678', '01012345678', 'Vodafone'],
    ['1112345678', true, '+201112345678', '01112345678', 'Etisalat'],
    ['1212345678', true, '+201212345678', '01212345678', 'Orange'],
    ['1512345678', true, '+201512345678', '01512345678', 'WE'],

    // 6. Arabic-Indic numerals
    ['٠١٠١٢٣٤٥٦٧٨', true, '+201012345678', '01012345678', 'Vodafone'],
    ['+٢٠١١١٢٣٤٥٦٧٨', true, '+201112345678', '01112345678', 'Etisalat'],
    ['٠٠٢٠١٥١٢٣٤٥٦٧٨', true, '+201512345678', '01512345678', 'WE'],

    // 7. Formatted with spaces, dashes, parentheses
    ['010 1234 5678', true, '+201012345678', '01012345678', 'Vodafone'],
    ['011-1234-5678', true, '+201112345678', '01112345678', 'Etisalat'],
    ['+20 (12) 1234-5678', true, '+201212345678', '01212345678', 'Orange'],
    ['(015) 1234 5678', true, '+201512345678', '01512345678', 'WE'],

    // 8. Invalid prefixes
    ['01312345678', false, null, null, null],
    ['01412345678', false, null, null, null],
    ['01612345678', false, null, null, null],
    ['01712345678', false, null, null, null],
    ['01812345678', false, null, null, null],
    ['01912345678', false, null, null, null],

    // 9. Invalid lengths
    ['0101234567', false, null, null, null],   // 10 digits
    ['010123456789', false, null, null, null], // 12 digits

    // 10. Landlines and non-mobiles
    ['0223456789', false, null, null, null],  // Cairo landline
    ['0345678901', false, null, null, null],  // Alexandria landline

    // 11. International non-Egyptian
    ['+966501234567', false, null, null, null], // Saudi Arabia
    ['+12025550123', false, null, null, null],  // USA

    // 12. Garbage / Empty
    ['', false, null, null, null],
    ['abc', false, null, null, null],
    ['010-text-5678', false, null, null, null],
]);

test('EgyptianPhone validates, normalizes, and classifies 38 test cases', function (
    string $input,
    bool $isValid,
    ?string $expectedE164,
    ?string $expectedNational,
    ?string $expectedNetwork
) {
    if (! $isValid) {
        expect(EgyptianPhone::isValid($input))->toBeFalse()
            ->and(EgyptianPhone::tryParse($input))->toBeNull();

        return;
    }

    expect(EgyptianPhone::isValid($input))->toBeTrue();

    $phone = EgyptianPhone::parse($input);

    expect($phone->e164)->toBe($expectedE164)
        ->and($phone->national)->toBe($expectedNational)
        ->and($phone->getNetwork())->toBe($expectedNetwork);
})->with('phone_cases');

test('EgyptianPhone computes deterministic HMAC-SHA256 hash', function () {
    $phone = EgyptianPhone::parse('01012345678');
    $hash1 = $phone->hash('secret-key');
    $hash2 = $phone->hash('secret-key');
    $hashDifferentKey = $phone->hash('different-key');

    expect($hash1)->toBe($hash2)
        ->and($hash1)->not->toBe($hashDifferentKey)
        ->and(strlen($hash1))->toBe(64);
});
