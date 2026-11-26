<?php

namespace Fleetbase\FleetOps\Support;

use Laravel\Sanctum\PersonalAccessToken;

/**
 * Tokens of the ShipCode driver app (/driver/v1).
 *
 * A driver-app token is a Sanctum token whose only ability is `driver-app`, named after the driver
 * profile it belongs to. It opens the driver routes, scoped to that driver's own work, and nothing
 * else: RejectDriverAppTokens refuses it on the company-wide public API (/v1) and the console API
 * (/int/v1), where any Sanctum token of a user would otherwise act as that user for the whole
 * company.
 */
class DriverAppToken
{
    public const ABILITY     = 'driver-app';
    public const NAME_PREFIX = 'driver-app:';
    public const TTL_DAYS    = 30;

    public static function nameFor(string $driverUuid): string
    {
        return static::NAME_PREFIX . $driverUuid;
    }

    public static function driverUuidOf(PersonalAccessToken $token): ?string
    {
        return str_starts_with((string) $token->name, static::NAME_PREFIX)
            ? substr($token->name, strlen(static::NAME_PREFIX))
            : null;
    }

    /**
     * A token limited to the driver app: it carries the driver-app ability and nothing wider.
     */
    public static function isDriverAppToken(?PersonalAccessToken $token): bool
    {
        if (!$token) {
            return false;
        }

        $abilities = (array) ($token->abilities ?? []);

        return in_array(static::ABILITY, $abilities, true) && !in_array('*', $abilities, true);
    }

    public static function isExpired(PersonalAccessToken $token): bool
    {
        if ($token->expires_at) {
            return $token->expires_at->isPast();
        }

        return $token->created_at && $token->created_at->copy()->addDays(static::TTL_DAYS)->isPast();
    }

    public static function find(?string $plainText): ?PersonalAccessToken
    {
        if (!$plainText) {
            return null;
        }

        try {
            return PersonalAccessToken::findToken($plainText);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
