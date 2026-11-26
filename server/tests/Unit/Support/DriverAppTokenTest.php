<?php

use Fleetbase\FleetOps\Http\Controllers\DriverApp\v1\DriverAppController;
use Fleetbase\FleetOps\Support\DriverAppToken;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

function fleetopsDriverAppTokenFixture(array $attributes): PersonalAccessToken
{
    $token = new PersonalAccessToken();
    $token->forceFill($attributes);

    return $token;
}

test('only tokens limited to the driver-app ability count as driver-app tokens', function () {
    expect(DriverAppToken::isDriverAppToken(fleetopsDriverAppTokenFixture(['abilities' => ['driver-app']])))->toBeTrue()
        ->and(DriverAppToken::isDriverAppToken(fleetopsDriverAppTokenFixture(['abilities' => ['*']])))->toBeFalse()
        ->and(DriverAppToken::isDriverAppToken(fleetopsDriverAppTokenFixture(['abilities' => ['driver-app', '*']])))->toBeFalse()
        ->and(DriverAppToken::isDriverAppToken(null))->toBeFalse();
});

test('driver-app token names carry the driver profile uuid', function () {
    $name = DriverAppToken::nameFor('2b1c0d4e-0000-4000-8000-000000000001');

    expect(DriverAppToken::driverUuidOf(fleetopsDriverAppTokenFixture(['name' => $name])))->toBe('2b1c0d4e-0000-4000-8000-000000000001')
        ->and(DriverAppToken::driverUuidOf(fleetopsDriverAppTokenFixture(['name' => 'some-other-token'])))->toBeNull();
});

test('driver-app tokens expire at expires_at, or after the default lifetime when it is missing', function () {
    Carbon::setTestNow('2026-11-26 12:00:00');

    expect(DriverAppToken::isExpired(fleetopsDriverAppTokenFixture(['expires_at' => '2026-11-26 11:59:00'])))->toBeTrue()
        ->and(DriverAppToken::isExpired(fleetopsDriverAppTokenFixture(['expires_at' => '2026-12-01 00:00:00'])))->toBeFalse()
        ->and(DriverAppToken::isExpired(fleetopsDriverAppTokenFixture(['created_at' => '2026-10-01 00:00:00'])))->toBeTrue()
        ->and(DriverAppToken::isExpired(fleetopsDriverAppTokenFixture(['created_at' => '2026-11-20 00:00:00'])))->toBeFalse();

    Carbon::setTestNow();
});

test('driver app accepts only real coordinates and normalizes Thai phone numbers', function () {
    $valid = new ReflectionMethod(DriverAppController::class, 'validPoint');
    $phone = new ReflectionMethod(DriverAppController::class, 'phone');

    expect($valid->invoke(null, 13.69, 100.75))->toBeTrue()
        ->and($valid->invoke(null, '13.69', '100.75'))->toBeTrue()
        ->and($valid->invoke(null, 0, 0))->toBeFalse()
        ->and($valid->invoke(null, 91, 100))->toBeFalse()
        ->and($valid->invoke(null, 13, 181))->toBeFalse()
        ->and($valid->invoke(null, null, 100))->toBeFalse()
        ->and($phone->invoke(null, '081-234-5678'))->toBe('+66812345678')
        ->and($phone->invoke(null, '+66812345678'))->toBe('+66812345678')
        ->and($phone->invoke(null, '66812345678'))->toBe('+66812345678');
});

test('driver app hands JSON requests on with the forwarded input, not the original body', function () {
    $original = Illuminate\Http\Request::create('/driver/v1/orders/order_x/activity', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['code' => 'started']));
    $forward  = (new ReflectionMethod(DriverAppController::class, 'forwardRequest'))
        ->invoke(null, $original, ['activity' => ['code' => 'started', 'status' => 'Order Started'], 'proof' => 'proof_1']);

    expect($original->isJson())->toBeTrue()
        ->and($original->input('activity'))->toBeNull()
        ->and($forward->input('activity'))->toBe(['code' => 'started', 'status' => 'Order Started'])
        ->and($forward->input('proof'))->toBe('proof_1')
        ->and($forward->input('code'))->toBeNull();
});
