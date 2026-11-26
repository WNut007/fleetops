<?php

use Fleetbase\FleetOps\Support\LiveOrderQuery;
use Illuminate\Database\Eloquent\Builder;

class FleetOpsLiveOrderQueryRecorder extends Builder
{
    public array $calls = [];

    public function __construct()
    {
    }

    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if (is_callable($column)) {
            $nested = new self();
            $column($nested);
            $this->calls[] = ['whereNested', $nested->calls];

            return $this;
        }

        $this->calls[] = ['where', $column, $operator, $value, $boolean];

        return $this;
    }

    public function whereHas($relation, ?Closure $callback = null, $operator = '>=', $count = 1)
    {
        $nested = null;

        if ($callback) {
            $nested = new self();
            $callback($nested);
        }

        $this->calls[] = ['whereHas', $relation, $nested?->calls, $operator, $count];

        return $this;
    }

    public function orWhereHas($relation, ?Closure $callback = null, $operator = '>=', $count = 1)
    {
        $this->calls[] = ['orWhereHas', $relation, $operator, $count];

        return $this;
    }

    public function whereNotIn($column, $values, $boolean = 'and')
    {
        $this->calls[] = ['whereNotIn', $column, $values, $boolean];

        return $this;
    }

    public function whereNull($column, $boolean = 'and', $not = false)
    {
        $this->calls[] = ['whereNull', $column, $boolean, $not];

        return $this;
    }

    public function whereNotNull($columns, $boolean = 'and')
    {
        $this->calls[] = ['whereNotNull', $columns];

        return $this;
    }

    public function whereExists($callback, $boolean = 'and', $not = false)
    {
        $this->calls[] = ['whereExists'];

        return $this;
    }

    public function applyDirectivesForPermissions(string $permission)
    {
        $this->calls[] = ['applyDirectivesForPermissions', $permission];

        return $this;
    }

    public function with($relations, $callback = null)
    {
        $this->calls[] = ['with', $relations];

        return $this;
    }
}

class FleetOpsLiveOrderQueryProbe extends LiveOrderQuery
{
    public static ?FleetOpsLiveOrderQueryRecorder $recorder = null;

    protected static function newOrderQuery(string $companyUuid): Builder
    {
        static::$recorder = new FleetOpsLiveOrderQueryRecorder();
        static::$recorder->where('company_uuid', $companyUuid);

        return static::$recorder;
    }
}

afterEach(function () {
    FleetOpsLiveOrderQueryProbe::$recorder = null;
});

test('live order query records base payload tracking permission and relation constraints', function () {
    $query = FleetOpsLiveOrderQueryProbe::make('company-uuid', [
        'exclude'           => ['order_abc1234'],
        'active'            => true,
        'unassigned'        => true,
        'with_relations'    => true,
        'apply_permissions' => true,
    ]);
    $calls = FleetOpsLiveOrderQueryProbe::$recorder->calls;

    expect($query)->toBe(FleetOpsLiveOrderQueryProbe::$recorder)
        ->and($calls)->toContain(['where', 'company_uuid', 'company-uuid', null, 'and'])
        ->and($calls)->toContain(['whereNotIn', 'status', LiveOrderQuery::$baseExcludedStatuses, 'and'])
        ->and($calls)->toContain(['whereNotIn', 'public_id', ['order_abc1234'], 'and'])
        ->and($calls)->toContain(['applyDirectivesForPermissions', 'fleet-ops list order'])
        ->and($calls)->toContain(['whereNotIn', 'status', LiveOrderQuery::$activeExcludedStatuses, 'and'])
        ->and($calls)->toContain(['whereNull', 'driver_assigned_uuid', 'and', false])
        ->and($calls)->toContain(['whereNull', 'deleted_at', 'and', false]);

    // Route and tracking number are column checks (OrderListScope), not per-order whereHas
    expect(collect($calls)->where(0, 'whereHas')->pluck(1)->all())->toBe(['driverAssigned'])
        ->and($calls)->toContain(['whereNotNull', 'orders.payload_uuid'])
        ->and($calls)->toContain(['whereNotNull', 'orders.tracking_number_uuid'])
        ->and($calls)->toContain(['whereExists']);

    $with          = collect($calls)->first(fn ($call) => $call[0] === 'with');
    $withRelations = array_merge(array_values(array_filter($with[1], 'is_string')), array_keys(array_filter($with[1], 'is_callable')));

    expect($withRelations)->toContain(
        'payload.entities',
        'payload.dropoff',
        'payload.pickup',
        'trackingNumber',
        'trackingStatuses',
        'driverAssigned',
        'vehicleAssigned',
        'customer',
        'facilitator'
    );
});

test('live order query can skip optional permission active unassigned and relation branches', function () {
    FleetOpsLiveOrderQueryProbe::make('company-uuid', [
        'active'            => false,
        'unassigned'        => false,
        'with_relations'    => false,
        'apply_permissions' => false,
    ]);

    $calls = FleetOpsLiveOrderQueryProbe::$recorder->calls;

    expect(collect($calls)->where(0, 'applyDirectivesForPermissions')->values())->toHaveCount(0)
        ->and(collect($calls)->where(0, 'with')->values())->toHaveCount(0)
        ->and(collect($calls)->where(0, 'whereHas')->pluck(1)->all())->not->toContain('driverAssigned')
        ->and(collect($calls)->where(0, 'whereNull')->pluck(1)->all())->not->toContain('driver_assigned_uuid')
        ->and(collect($calls)->where(0, 'whereNotIn')->values())->toHaveCount(1);
});
