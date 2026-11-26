<?php

namespace Fleetbase\FleetOps\Support;

/**
 * The "real order" condition of order lists and live counts: the order has a route (pickup,
 * dropoff or waypoints) and a tracking number.
 *
 * Checked on the order and payload columns instead of whereHas into places, tracking numbers and
 * tracking statuses: those correlated subqueries ran for every order of the company on each count
 * (≈6 index lookups per order, ~470 ms at 20k orders) and kept growing with the order history.
 * Difference: an order whose pickup/dropoff place was later deleted now stays listed.
 */
class OrderListScope
{
    /**
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder|mixed $builder
     */
    public static function routeAndTracking($builder, string $ordersTable = 'orders')
    {
        $builder->whereNotNull($ordersTable . '.payload_uuid');
        $builder->whereNotNull($ordersTable . '.tracking_number_uuid');
        $builder->whereExists(function ($payloadQuery) use ($ordersTable) {
            $payloadQuery->selectRaw('1')
                ->from('payloads')
                ->whereColumn('payloads.uuid', $ordersTable . '.payload_uuid')
                ->where(function ($q) {
                    $q->whereNotNull('payloads.pickup_uuid')
                        ->orWhereNotNull('payloads.dropoff_uuid')
                        ->orWhereExists(function ($w) {
                            $w->selectRaw('1')
                                ->from('waypoints')
                                ->whereColumn('waypoints.payload_uuid', 'payloads.uuid')
                                ->whereNull('waypoints.deleted_at');
                        });
                });
        });

        return $builder;
    }
}
