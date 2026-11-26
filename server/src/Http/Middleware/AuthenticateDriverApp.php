<?php

namespace Fleetbase\FleetOps\Http\Middleware;

use Fleetbase\FleetOps\Http\Controllers\Api\v1\DriverController;
use Fleetbase\FleetOps\Models\Driver;
use Fleetbase\FleetOps\Support\DriverAppToken;
use Fleetbase\Models\User;
use Fleetbase\Support\Auth;
use Illuminate\Http\Request;

/**
 * Authenticates /driver/v1 requests with a driver-app token and sets the session to the driver's
 * company and user. The driver is kept on the request (`driver_app.driver`) for the controller,
 * which only ever reads and changes that driver's own records.
 *
 * Refused (401): no token, a token that is not a driver-app token, expired, its user deactivated
 * or gone, or its driver profile deleted.
 */
class AuthenticateDriverApp
{
    public const DRIVER_ATTRIBUTE = 'driver_app.driver';

    public function handle(Request $request, \Closure $next)
    {
        $token = DriverAppToken::find($request->bearerToken());

        if (!DriverAppToken::isDriverAppToken($token) || DriverAppToken::isExpired($token)) {
            return static::unauthorized();
        }

        $user = $token->tokenable;
        if (!$user instanceof User || DriverController::isLoginDeactivated($user)) {
            return static::unauthorized();
        }

        $driverUuid = DriverAppToken::driverUuidOf($token);
        $driver     = $driverUuid ? Driver::where('uuid', $driverUuid)->where('user_uuid', $user->uuid)->first() : null;
        if (!$driver) {
            return static::unauthorized();
        }

        // Session as the driver's user in the driver's company (a user can drive for several)
        Auth::setSession($user);
        session(['company' => $driver->company_uuid, 'user' => $user->uuid, 'driver' => $driver->uuid]);
        // Live environment, keyed by this token (never the company's API credential)
        Auth::setApiKey($token);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set(static::DRIVER_ATTRIBUTE, $driver);

        // Recorded at most once a minute: the app calls every few seconds while driving
        if (!$token->last_used_at || $token->last_used_at->diffInSeconds(now()) > 60) {
            $token->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        return $next($request);
    }

    protected static function unauthorized()
    {
        return response()->json(['errors' => ['Unauthenticated.']], 401);
    }
}
