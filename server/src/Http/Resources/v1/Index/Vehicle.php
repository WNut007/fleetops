<?php

namespace Fleetbase\FleetOps\Http\Resources\v1\Index;

use Fleetbase\FleetOps\Models\Order;
use Fleetbase\FleetOps\Support\TrailerSummary;
use Fleetbase\FleetOps\Support\Utils;
use Fleetbase\Http\Resources\FleetbaseResource;
use Fleetbase\Support\Http;

/**
 * Lightweight Vehicle resource for index views.
 * Only includes essential identification and display information.
 */
class Vehicle extends FleetbaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     */
    public function toArray($request): array
    {
        $isInternal = Http::isInternalRequest();

        return [
            'id'                    => $this->when($isInternal, $this->id, $this->public_id),
            'uuid'                  => $this->when($isInternal, $this->uuid),
            'public_id'             => $this->when($isInternal, $this->public_id),
            'company_uuid'          => $this->when($isInternal, $this->company_uuid),
            'vendor_uuid'           => $this->when($isInternal, $this->vendor_uuid),
            'photo_uuid'            => $this->when($isInternal, $this->photo_uuid),
            'internal_id'           => $this->internal_id,
            'display_name'          => $this->display_name,
            'driver_name'           => $this->driver_name,
            'plate_number'          => $this->plate_number,
            'serial_number'         => $this->serial_number,
            'fuel_card_number'      => $this->fuel_card_number,
            'vin'                   => $this->vin,
            'make'                  => $this->make,
            'model'                 => $this->model,
            'year'                  => $this->year,
            'photo_url'             => $this->photo_url,
            'status'                => $this->status,
            'location'              => Utils::castPoint($this->location),
            'heading'               => (int) data_get($this, 'heading', 0),
            'altitude'              => (int) data_get($this, 'altitude', 0),
            'speed'                 => (int) data_get($this, 'speed', 0),
            'online'                => (bool) data_get($this, 'online', false),
            'devices'               => $this->whenLoaded('devices', fn () => $this->compactDevices()),
            'trailers'              => $this->whenLoaded('currentTrailers', fn () => $this->compactTrailers()),
            'assigned_orders_count' => $this->when($isInternal, $this->assignedOrdersCount()),

            // Meta flag to indicate this is an index resource
            'meta'            => [
                '_index_resource'          => true,
                'current_order_reference'  => $this->currentOrderReference(),
                'location_coordinates'     => $this->locationCoordinates(),
                'speed_label'              => $this->speedLabel(),
                'heading_label'            => $this->headingLabel(),
                'status_label'             => $this->statusLabel(),
            ],
        ];
    }

    /** Request attribute holding the per-vehicle values preload() computed for a list page. */
    protected const PRELOAD_KEY = 'fleetops.index-vehicle.preload';

    protected function assignedOrdersCount(): int
    {
        $preloaded = static::preloaded('orders_count');
        if ($preloaded !== null && array_key_exists($this->uuid, $preloaded)) {
            return $preloaded[$this->uuid];
        }

        return Order::where('vehicle_assigned_uuid', $this->uuid)->count();
    }

    /**
     * Load what this resource reads for every vehicle of a list page in a few queries
     * instead of several per row: the driver with its user and current order, the number
     * of orders, and the latest unfinished order. Adds no relation that the resource prints.
     */
    public static function preload(\Illuminate\Database\Eloquent\Collection $vehicles): void
    {
        if ($vehicles->isEmpty()) {
            return;
        }

        $vehicles->loadMissing(['driver.user', 'driver.currentOrder.trackingNumber']);

        $uuids  = $vehicles->pluck('uuid')->filter()->unique()->values()->all();
        $counts = Order::whereIn('vehicle_assigned_uuid', $uuids)
            ->groupBy('vehicle_assigned_uuid')
            ->selectRaw('vehicle_assigned_uuid, count(*) as aggregate')
            ->pluck('aggregate', 'vehicle_assigned_uuid')
            ->map(fn ($count) => (int) $count)
            ->all();

        // Pick each vehicle's newest unfinished order from slim rows, then load only those orders
        // in full: a busy fleet has thousands of unfinished orders, and hydrating all of them
        // (with tracking numbers) to keep one per vehicle was most of this request.
        $newest = static::activeOrdersQuery()
            ->whereIn('vehicle_assigned_uuid', $uuids)
            ->get(['id', 'uuid', 'vehicle_assigned_uuid', 'created_at'])
            ->groupBy('vehicle_assigned_uuid')
            ->map(fn ($orders) => $orders->first()->uuid);

        $orders = $newest->isEmpty() ? collect() : Order::whereIn('uuid', $newest->values()->all())
            ->with('trackingNumber')
            ->get()
            ->keyBy('uuid');

        $active = $newest->map(fn ($orderUuid) => $orders->get($orderUuid))->all();

        $values = ['orders_count' => [], 'active_order' => []];
        foreach ($uuids as $uuid) {
            $values['orders_count'][$uuid] = $counts[$uuid] ?? 0;
            $values['active_order'][$uuid] = $active[$uuid] ?? null;
        }

        app('request')->attributes->set(static::PRELOAD_KEY, $values);
    }

    protected static function preloaded(string $name): ?array
    {
        $values = app('request')->attributes->get(static::PRELOAD_KEY);

        return is_array($values) ? ($values[$name] ?? null) : null;
    }

    /**
     * Unfinished orders of a vehicle, newest first (id breaks ties between orders created
     * in the same second, so the bulk and single lookups pick the same one).
     */
    protected static function activeOrdersQuery()
    {
        return Order::whereNotIn('status', ['completed', 'canceled', 'cancelled'])
            ->latest()
            ->orderByDesc('id');
    }

    protected function compactDevices(): array
    {
        return $this->devices->map(
            fn ($device) => [
                'id'            => $device->uuid,
                'uuid'          => $device->uuid,
                'public_id'     => $device->public_id,
                'name'          => $device->name,
                'display_name'  => $device->display_name,
                'device_id'     => $device->device_id,
                'serial_number' => $device->serial_number,
                'imei'          => $device->imei,
                'provider'      => $device->provider,
                'status'        => $device->status,
            ]
        )->values()->all();
    }

    /**
     * Trailers currently coupled to the vehicle, compacted for map popovers.
     */
    protected function compactTrailers(): array
    {
        return TrailerSummary::collection($this->currentTrailers);
    }

    protected function currentOrderReference(): ?string
    {
        $this->loadMissing('driver.currentOrder');
        $order = data_get($this, 'driver.currentOrder') ?? $this->currentPositionOrder() ?? $this->currentVehicleOrder();

        return data_get($order, 'tracking') ?? data_get($order, 'public_id');
    }

    protected function locationCoordinates(): ?string
    {
        $location = Utils::castPoint($this->location);

        return $location ? $this->formatCoordinate($location->getLat()) . ' ' . $this->formatCoordinate($location->getLng()) : null;
    }

    protected function speedLabel(): string
    {
        $speed = data_get($this->lastKnownPosition(), 'speed', data_get($this, 'speed'));

        return is_numeric($speed) ? ((int) $speed) . ' km/h' : '-';
    }

    protected function headingLabel(): string
    {
        $heading = data_get($this->lastKnownPosition(), 'heading', data_get($this, 'heading'));

        return is_numeric($heading) ? ((int) $heading) . ' deg' : '-';
    }

    protected function statusLabel(): ?string
    {
        return $this->status ? str($this->status)->replace(['_', '-'], ' ')->headline()->toString() : null;
    }

    protected function currentPositionOrder(): ?Order
    {
        $orderUuid = data_get($this->lastKnownPosition(), 'order_uuid');

        return $orderUuid ? Order::where('uuid', $orderUuid)->first() : null;
    }

    protected function currentVehicleOrder(): ?Order
    {
        $preloaded = static::preloaded('active_order');
        if ($preloaded !== null && array_key_exists($this->uuid, $preloaded)) {
            return $preloaded[$this->uuid];
        }

        return static::activeOrdersQuery()
            ->where('vehicle_assigned_uuid', $this->uuid)
            ->first();
    }

    protected function lastKnownPosition()
    {
        if (!$this->resource->relationLoaded('last_known_position')) {
            $this->resource->setRelation('last_known_position', $this->getLastKnownPosition());
        }

        return $this->resource->getRelation('last_known_position');
    }

    protected function formatCoordinate(float $coordinate): string
    {
        return (string) round($coordinate, 4);
    }
}
