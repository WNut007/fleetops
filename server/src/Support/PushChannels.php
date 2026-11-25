<?php

namespace Fleetbase\FleetOps\Support;

use NotificationChannels\Apn\ApnChannel;
use NotificationChannels\Fcm\FcmChannel;

/**
 * Drops the push channels (FCM, APN) that are not set up, so a notification without Firebase or
 * Apple credentials does not queue a job that always fails ("Unable to determine the Firebase
 * Project ID"). The other channels (mail, broadcast) are unaffected. Credentials come from the env
 * or from Admin → System Config → push notifications, which writes the same config keys.
 */
class PushChannels
{
    public static function filter(array $channels): array
    {
        return array_values(array_filter($channels, function ($channel) {
            if ($channel === FcmChannel::class) {
                return static::fcmConfigured();
            }
            if ($channel === ApnChannel::class) {
                return static::apnConfigured();
            }

            return true;
        }));
    }

    public static function fcmConfigured(): bool
    {
        $credentials = config('firebase.projects.' . config('firebase.default', 'app') . '.credentials');

        return !empty($credentials);
    }

    public static function apnConfigured(): bool
    {
        $apn = config('broadcasting.connections.apn', []);

        return !empty(data_get($apn, 'key_id'))
            && !empty(data_get($apn, 'team_id'))
            && (!empty(data_get($apn, 'private_key_content')) || !empty(data_get($apn, 'private_key_path')));
    }
}
