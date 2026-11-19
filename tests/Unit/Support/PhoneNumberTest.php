<?php

use App\Support\PhoneNumber;

test('Brazilian numbers are normalized to E.164', function (string $input, ?string $expected) {
    expect(PhoneNumber::toE164($input))->toBe($expected);
})->with([
    'masked mobile' => ['(11) 98765-4321', '+5511987654321'],
    'masked landline' => ['(11) 3456-7890', '+551134567890'],
    'digits only' => ['11987654321', '+5511987654321'],
    'with country code' => ['+55 11 98765-4321', '+5511987654321'],
    'international prefix 00' => ['00 1 212 555 0100', '+12125550100'],
    'other country' => ['+351 912 345 678', '+351912345678'],
    'too short' => ['98765', null],
    'too long' => ['+55 11 98765-4321 1234 5', null],
    'letters' => ['call me', null],
]);
