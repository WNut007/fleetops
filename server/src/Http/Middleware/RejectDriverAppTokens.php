<?php

namespace Fleetbase\FleetOps\Http\Middleware;

use Fleetbase\FleetOps\Support\DriverAppToken;
use Illuminate\Http\Request;

/**
 * Refuses driver-app tokens on the company-wide APIs (/v1 public API, /int/v1 console API).
 *
 * Both accept any Sanctum token of a user and then act as that user for the whole company, so a
 * driver-app token reaching them would see every order, driver and customer. The driver app has
 * its own routes (/driver/v1) that only show the driver's own work.
 */
class RejectDriverAppTokens
{
    public function handle(Request $request, \Closure $next)
    {
        if (DriverAppToken::isDriverAppToken(DriverAppToken::find($request->bearerToken()))) {
            return response()->json(['errors' => ['This token can only be used with the driver app API.']], 401);
        }

        return $next($request);
    }
}
