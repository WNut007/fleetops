<?php

use Fleetbase\FleetOps\Http\Controllers\Internal\v1\DriverController;
use Illuminate\Http\Request;

test('the console driver list eager-loads what each listed driver serializes', function () {
    $query = new class {
        public array $calls = [];

        public function __call(string $method, array $arguments): self
        {
            $this->calls[] = [$method, $arguments[0] ?? null];

            return $this;
        }
    };

    DriverController::onQueryRecord($query, new Request());

    expect($query->calls)->toBe([
        ['with', ['user', 'vehicle', 'vendor', 'currentOrder']],
        ['withCount', 'orders'],
    ]);
});
