<?php

namespace Fleetbase\FleetOps\Http\Resources\v1;

use Fleetbase\FleetOps\Support\ProfileAccountManager;
use Fleetbase\FleetOps\Support\Utils;
use Fleetbase\Http\Resources\FleetbaseResource;
use Fleetbase\Http\Resources\FleetbaseResourceCollection;
use Fleetbase\Http\Resources\User;
use Fleetbase\LaravelMysqlSpatial\Types\Point;
use Fleetbase\Support\Http;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class Driver extends FleetbaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function toArray($request)
    {
        return $this->withCustomFields([
            'id'                            => $this->when(Http::isInternalRequest(), $this->id, $this->public_id),
            'uuid'                          => $this->when(Http::isInternalRequest(), $this->uuid),
            'user_uuid'                     => $this->when(Http::isInternalRequest(), $this->user_uuid),
            'company_uuid'                  => $this->when(Http::isInternalRequest(), $this->company_uuid),
            'vehicle_uuid'                  => $this->when(Http::isInternalRequest(), $this->vehicle_uuid),
            'vendor_uuid'                   => $this->when(Http::isInternalRequest(), $this->vendor_uuid),
            'current_job_uuid'              => $this->when(Http::isInternalRequest(), $this->current_job_uuid),
            'public_id'                     => $this->when(Http::isInternalRequest(), $this->public_id),
            'user'                          => $this->when(Http::isPublicRequest(), fn () => $this->user ? $this->user->public_id : null, fn () => $this->userPayload($request)),
            'internal_id'                   => $this->internal_id,
            'is_staff_linked'               => $this->when(Http::isInternalRequest(), fn () => ProfileAccountManager::isStaffAccount($this->user)),
            'login_status'                  => $this->when(Http::isInternalRequest(), fn () => data_get($this, 'user.status')),
            'company'                       => $this->when(Http::isPublicRequest(), fn () => $this->company ? $this->company->public_id : null),
            'company_name'                  => $this->when(Http::isPublicRequest(), fn () => $this->company ? $this->company->name : null),
            'name'                          => $this->name,
            'email'                         => $this->email,
            'phone'                         => $this->phone,
            'timezone'                      => data_get($this, 'user.timezone'),
            'drivers_license_number'        => $this->drivers_license_number,
            'license_expiry'                => $this->formatDateOnly($this->license_expiry),
            'photo_url'                     => $this->photo_url,
            'avatar_url'                    => $this->avatar_url,
            'avatar_value'                  => $this->when(Http::isInternalRequest(), fn () => $this->avatarValue()),
            'vehicle_name'                  => $this->when(Http::isInternalRequest(), $this->vehicle_name),
            'vehicle_avatar'                => $this->vehicle_avatar,
            'vendor_name'                   => $this->when(Http::isInternalRequest(), $this->vendor_name),
            'assigned_orders_count'         => $this->when(Http::isInternalRequest(), fn () => $this->assignedOrdersCount()),
            'current_order_reference'       => $this->when(Http::isInternalRequest(), fn () => $this->currentOrderReference()),
            'vehicle'                       => $this->whenLoaded('vehicle', fn () => new VehicleWithoutDriver($this->vehicle)),
            'current_job'                   => $this->whenLoaded('currentJob', fn () => new Order($this->currentJob)),
            'current_job_id'                => $this->when(Http::isInternalRequest(), data_get($this, 'currentJob.tracking')),
            'jobs'                          => $this->whenLoaded('jobs', fn () => $this->getJobs()),
            'vendor'                        => $this->whenLoaded('vendor', fn () => new Vendor($this->vendor)),
            // Public callers receive each assignment as a public id so a write
            // can be read back; the nested objects above are unchanged.
            'vehicle_id'                    => $this->when(Http::isPublicRequest(), fn () => $this->vehicle_id),
            'vendor_id'                     => $this->when(Http::isPublicRequest(), fn () => $this->vendor_id),
            'job_id'                        => $this->when(Http::isPublicRequest(), fn () => data_get($this, 'currentJob.public_id')),
            'fleets'                        => $this->whenLoaded('fleets', fn () => Fleet::collection($this->resource->getRelation('fleets'))),
            'current_shift'                 => $this->whenLoaded('currentShift', fn () => $this->currentShift),
            'location'                      => $this->wasRecentlyCreated ? new Point(0, 0) : Utils::castPoint($this->location),
            'heading'                       => (int) data_get($this, 'heading', 0),
            'bearing'                       => data_get($this, 'bearing'),
            'altitude'                      => (int) data_get($this, 'altitude', 0),
            'speed'                         => (int) data_get($this, 'speed', 0),
            'country'                       => data_get($this, 'country'),
            'currency'                      => data_get($this, 'currency', Utils::getCurrenyFromCountryCode($this->country)),
            'city'                          => data_get($this, 'city', Utils::getCapitalCityFromCountryCode($this->country)),
            'online'                        => data_get($this, 'online', false),
            'current_status'                => data_get($this, 'current_status'),
            'status'                        => $this->status,
            'token'                         => $this->token,
            // Orchestrator constraints
            'skills'                        => data_get($this, 'skills'),
            'max_travel_time'               => $this->max_travel_time,
            'max_distance'                  => $this->max_distance,
            'time_window_start'             => $this->time_window_start,
            'time_window_end'               => $this->time_window_end,
            'meta'                          => data_get($this, 'meta', Utils::createObject()),
            'updated_at'                    => $this->updated_at,
            'created_at'                    => $this->created_at,
        ]);
    }

    protected function assignedOrdersCount(): int
    {
        // DriverFilter adds orders_count to listed drivers; a single record counts on demand.
        if (isset($this->resource->orders_count)) {
            return (int) $this->resource->orders_count;
        }

        return $this->orders()->count();
    }

    /**
     * The driver's login account. A request for this one driver gets the full user; anything
     * else (a list of drivers) gets the account without its role, policies and permissions,
     * which cost several queries per driver and which no listing shows (about 7 KB each).
     */
    protected function userPayload($request)
    {
        $user = $this->user;
        if (!$user || $this->isRequestForThisDriver($request)) {
            return new User($user);
        }

        $payload = [];
        foreach (static::LISTED_USER_FIELDS as $field) {
            $payload[$field] = data_get($user, $field);
        }
        $payload['id']   = data_get($user, 'uuid');
        $payload['meta'] = data_get($user, 'meta') ?? Utils::createObject();

        return $payload;
    }

    /**
     * User fields sent with each driver in a list: plain columns only, nothing that queries.
     */
    public const LISTED_USER_FIELDS = [
        'uuid', 'company_uuid', 'public_id', 'name', 'username', 'email', 'phone', 'country', 'timezone', 'avatar_url', 'type',
        'is_online', 'email_verified_at', 'phone_verified_at', 'last_seen_at', 'last_login', 'status', 'slug', 'updated_at', 'created_at',
    ];

    /**
     * Whether the request addresses this driver by id (/drivers/{id}), not a list of drivers.
     */
    protected function isRequestForThisDriver($request): bool
    {
        $last = $request ? basename((string) $request->path()) : '';

        return $last !== '' && in_array($last, array_filter([data_get($this->resource, 'public_id'), data_get($this->resource, 'uuid')]), true);
    }

    protected function currentOrderReference(): ?string
    {
        $this->loadMissing('currentOrder');
        $order = data_get($this, 'currentOrder');

        return data_get($order, 'tracking') ?? data_get($order, 'public_id');
    }

    /**
     * The jobs that were eager-loaded with the driver (only called when `jobs` is loaded).
     * This used to re-query every job of the driver, once per driver and each with its
     * assigned driver: it ignored any constraint the controller put on the eager load
     * (e.g. unfinished jobs only) and made fleet/driver lists take several seconds.
     */
    public function getJobs(): AnonymousResourceCollection|FleetbaseResourceCollection
    {
        return Order::collection($this->resource->getRelation('jobs'));
    }

    protected function formatDateOnly($date): ?string
    {
        if (!$date) {
            return null;
        }

        return method_exists($date, 'toDateString') ? $date->toDateString() : (string) $date;
    }

    /**
     * Transform the resource into an webhook payload.
     *
     * @return array
     */
    public function toWebhookPayload()
    {
        return [
            'id'                     => $this->public_id,
            'internal_id'            => $this->internal_id,
            'name'                   => $this->name,
            'email'                  => $this->email,
            'phone'                  => $this->phone,
            'photo_url'              => $this->photo_url,
            'license_expiry'         => $this->formatDateOnly($this->license_expiry),
            'vehicle'                => data_get($this, 'vehicle.public_id'),
            'current_job'            => data_get($this, 'currentJob.public_id'),
            'vendor'                 => data_get($this, 'vendor.public_id'),
            'location'               => Utils::castPoint($this->location),
            'heading'                => (int) data_get($this, 'heading', 0),
            'altitude'               => (int) data_get($this, 'altitude', 0),
            'speed'                  => (int) data_get($this, 'speed', 0),
            'country'                => data_get($this, 'country'),
            'currency'               => data_get($this, 'currency', Utils::getCurrenyFromCountryCode($this->country)),
            'city'                   => data_get($this, 'city', Utils::getCapitalCityFromCountryCode($this->country)),
            'online'                 => data_get($this, 'online', false),
            'current_status'         => data_get($this, 'current_status'),
            'status'                 => $this->status,
            'skills'                 => data_get($this, 'skills'),
            'max_travel_time'        => $this->max_travel_time,
            'max_distance'           => $this->max_distance,
            'time_window_start'      => $this->time_window_start,
            'time_window_end'        => $this->time_window_end,
            'meta'                   => data_get($this, 'meta', Utils::createObject()),
            'updated_at'             => $this->updated_at,
            'created_at'             => $this->created_at,
        ];
    }

    /**
     * The stored avatar as the avatar picker expects it. getOriginal() rebuilds a fresh model
     * to run the accessor on, so every row looked its relations up again (101 vehicle queries
     * on the live driver list); unless the value was changed it equals the accessor here.
     */
    protected function avatarValue()
    {
        return $this->resource->isDirty('avatar_url') ? $this->resource->getOriginal('avatar_url') : $this->resource->avatar_url;
    }
}
