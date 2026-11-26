<?php

namespace Fleetbase\FleetOps\Support;

use Fleetbase\FleetOps\Http\Resources\v1\Index\Customer as CustomerIndexResource;
use Fleetbase\FleetOps\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LiveOrderQuery
{
    public static array $baseExcludedStatuses   = ['canceled', 'completed', 'expired'];
    public static array $activeExcludedStatuses = ['created', 'completed', 'expired', 'order_canceled', 'canceled', 'pending'];

    public static function make(?string $companyUuid = null, array $options = []): Builder
    {
        $companyUuid       = $companyUuid ?: session('company');
        $exclude           = $options['exclude'] ?? [];
        $active            = $options['active'] ?? false;
        $unassigned        = $options['unassigned'] ?? false;
        $withRelations     = $options['with_relations'] ?? false;
        $applyPermissions  = $options['apply_permissions'] ?? true;

        // Route and tracking number checked on columns (see OrderListScope), not whereHas
        // subqueries per order that made the live counts grow with the order history
        $query = static::newOrderQuery($companyUuid)
            ->whereNotIn('status', static::$baseExcludedStatuses)
            ->whereNull('deleted_at');
        OrderListScope::routeAndTracking($query);

        if (!empty($exclude)) {
            $query->whereNotIn('public_id', $exclude);
        }

        if ($applyPermissions) {
            $query->applyDirectivesForPermissions('fleet-ops list order');
        }

        if ($active) {
            $query->whereHas('driverAssigned');
            $query->whereNotIn('status', static::$activeExcludedStatuses);
        }

        if ($unassigned) {
            $query->whereNull('driver_assigned_uuid');
        }

        if ($withRelations) {
            $query->with([
                'payload.entities',
                'payload.dropoff',
                'payload.pickup',
                'payload.return',
                'payload.firstWaypointMarker.place',
                'payload.lastWaypointMarker.place',
                'trackingNumber',
                'trackingStatuses',
                'driverAssigned' => function ($query) {
                    $query->without(['jobs', 'currentJob']);
                },
                'vehicleAssigned' => function ($query) {
                    $query->without(['fleets', 'vendor']);
                },
                'customer' => static fn (MorphTo $relation) => CustomerIndexResource::withPhoto($relation),
                'facilitator',
            ]);
        }

        return $query;
    }

    protected static function newOrderQuery(string $companyUuid): Builder
    {
        return Order::where('company_uuid', $companyUuid);
    }
}
