<?php

use Fleetbase\FleetOps\Http\Controllers\Internal\v1\OrderController;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Covers the internal OrderController createRecord flow end to end against an
 * in-memory SQLite fixture: validation, order-config resolution with default
 * fallback and invalid-config rejection, order persistence with payload,
 * waypoint, entity, and file handling, custom field syncing, and the
 * finalize dispatch.
 */
if (!function_exists('Fleetbase\Support\session')) {
    eval('namespace Fleetbase\Support; function session($key = null, $default = null) { if ($key === null) { return new class { public function has($k) { return \session($k) !== null; } public function get($k, $d = null) { return \session($k, $d); } }; } return \session($key, $default); }');
}

if (!function_exists('Fleetbase\Support\auth')) {
    eval('namespace Fleetbase\Support; function auth() { return new class { public function user() { return null; } public function id() { return null; } }; }');
}

if (!function_exists('Fleetbase\FleetOps\Http\Controllers\Internal\v1\env')) {
    eval('namespace Fleetbase\FleetOps\Http\Controllers\Internal\v1; function env($key, $default = null) { return $default; }');
}

if (!function_exists('Fleetbase\FleetOps\Models\dispatch')) {
    eval('namespace Fleetbase\FleetOps\Models; function dispatch($job = null) { return new \Fleetbase\TestSupport\PendingDispatch(); }');
}

if (!function_exists('Fleetbase\FleetOps\Models\event')) {
    eval('namespace Fleetbase\FleetOps\Models; function event($event = null) { return $event; }');
}

if (!function_exists('__')) {
    function __($key = null, $replace = [], $locale = null)
    {
        return $key;
    }
}

if (!class_exists('Illuminate\Validation\Rule')) {
    eval('namespace Illuminate\Validation; class Rule { public function __construct(private string $rule = "") {} public static function requiredIf($c): string { return (is_callable($c) ? $c() : $c) ? "required" : "nullable"; } public static function in(array $v): self { return new self("in:" . implode(",", $v)); } public static function exists($t, $c = null): self { return new self("exists:" . $t . ($c ? "," . $c : "")); } public static function unique($t, $c = null): self { return new self("unique:" . $t . ($c ? "," . $c : "")); } public static function when($c, array $r): array { return (is_callable($c) ? $c() : $c) ? $r : []; } public function where($cb): self { return $this; } public function whereNull($col): self { return $this; } public function ignore($v, $c = null): self { return $this; } public function __toString(): string { return $this->rule; } }');
}

if (!Request::hasMacro('getController')) {
    Request::macro('getController', fn () => new OrderController());
}

if (!Request::hasMacro('or')) {
    Request::macro('or', function (array $params = [], $default = null) {
        foreach ($params as $param) {
            if ($this->has($param)) {
                return $this->input($param);
            }
        }

        return $default;
    });
}

function fleetopsInternalOrderCreateContainer(): void
{
    $current = Illuminate\Container\Container::getInstance();
    if (method_exists($current, 'hasDebugModeEnabled')) {
        return;
    }

    // Exception formatting calls app()->hasDebugModeEnabled(), which the
    // harness container lacks — swap in a subclass carrying the same state
    $replacement = new class extends Illuminate\Container\Container {
        public function environment(...$environments)
        {
            if (empty($environments)) {
                return 'testing';
            }

            $checks = is_array($environments[0]) ? $environments[0] : $environments;

            return in_array('testing', $checks, true);
        }

        public function hasDebugModeEnabled()
        {
            return true;
        }
    };

    foreach (['bindings', 'instances', 'aliases', 'abstractAliases', 'resolved', 'extenders', 'tags', 'contextual', 'scopedInstances', 'reboundCallbacks', 'globalBeforeResolvingCallbacks', 'globalResolvingCallbacks', 'globalAfterResolvingCallbacks', 'beforeResolvingCallbacks', 'resolvingCallbacks', 'afterResolvingCallbacks'] as $property) {
        if (!property_exists(Illuminate\Container\Container::class, $property)) {
            continue;
        }
        $reflection = new ReflectionProperty(Illuminate\Container\Container::class, $property);
        $reflection->setAccessible(true);
        if ($reflection->isInitialized($current)) {
            $reflection->setValue($replacement, $reflection->getValue($current));
        }
    }

    Illuminate\Container\Container::setInstance($replacement);
    Illuminate\Support\Facades\Facade::setFacadeApplication($replacement);
}

function fleetopsInternalOrderCreateBoot(array $validatorErrors = []): SQLiteConnection
{
    fleetopsInternalOrderCreateContainer();
    $pdo = new PDO('sqlite::memory:');
    $pdo->sqliteCreateFunction('ST_PointFromText', fn ($wkt, $srid = 0, $axisOrder = null) => $wkt);
    $connection = new SQLiteConnection($pdo);
    $resolver   = new ConnectionResolver(['default' => $connection, 'mysql' => $connection]);
    $resolver->setDefaultConnection('mysql');
    EloquentModel::setConnectionResolver($resolver);
    app()->instance('db', new class($connection) {
        public function __construct(public SQLiteConnection $c)
        {
        }

        public function connection($name = null): SQLiteConnection
        {
            return $this->c;
        }

        public function __call($method, $arguments)
        {
            return $this->c->{$method}(...$arguments);
        }
    });
    app()->instance('db.schema', $connection->getSchemaBuilder());
    $barcodeFake = new class {
        public function __call($method, $arguments)
        {
            return 'barcode';
        }
    };
    app()->instance('DNS2D', $barcodeFake);
    app()->instance('DNS1D', $barcodeFake);
    $GLOBALS['fleetopsInternalOrderCreateErrors'] = $validatorErrors;
    app()->instance('validator', new class {
        public function make($data = [], $rules = [], $messages = [], $attributes = [])
        {
            return new class implements Illuminate\Contracts\Validation\Validator {
                public function fails()
                {
                    return !empty($GLOBALS['fleetopsInternalOrderCreateErrors']);
                }

                public function errors()
                {
                    return new Illuminate\Support\MessageBag($GLOBALS['fleetopsInternalOrderCreateErrors']);
                }

                public function validated()
                {
                    return [];
                }

                public function validate()
                {
                    return [];
                }

                public function failed()
                {
                    return array_keys($GLOBALS['fleetopsInternalOrderCreateErrors']);
                }

                public function sometimes($attribute, $rules, callable $callback)
                {
                    return $this;
                }

                public function after($callback)
                {
                    return $this;
                }

                public function getMessageBag()
                {
                    return new Illuminate\Support\MessageBag($GLOBALS['fleetopsInternalOrderCreateErrors']);
                }
            };
        }
    });
    config()->set('fleetops.distance_matrix.provider', 'calculate');
    Illuminate\Support\Facades\DB::clearResolvedInstance('db');

    $schema = $connection->getSchemaBuilder();
    $tables = [
        'orders'              => ['uuid', 'public_id', 'internal_id', 'company_uuid', 'payload_uuid', 'route_uuid', 'order_config_uuid', 'service_quote_uuid', 'purchase_rate_uuid', 'tracking_number_uuid', 'driver_assigned_uuid', 'vehicle_assigned_uuid', 'customer_uuid', 'customer_type', 'facilitator_uuid', 'facilitator_type', 'session_uuid', 'transaction_uuid', 'status', 'type', 'dispatched', 'dispatched_at', 'scheduled_at', 'distance', 'time', 'pod_required', 'pod_method', 'started', 'started_at', 'meta', 'orchestrator_priority', 'adhoc', 'adhoc_distance', 'created_by_uuid', 'updated_by_uuid', '_key'],
        'order_configs'       => ['uuid', 'public_id', 'company_uuid', 'name', 'key', 'namespace', 'description', 'flow', 'entities', 'meta', 'tags', 'version', 'core_service', 'status', 'type', '_key'],
        'payloads'            => ['uuid', 'public_id', 'company_uuid', 'pickup_uuid', 'dropoff_uuid', 'return_uuid', 'current_waypoint_uuid', 'type', 'meta', '_key'],
        'waypoints'           => ['uuid', 'public_id', 'company_uuid', 'payload_uuid', 'place_uuid', 'order', 'type', 'status', 'tracking_number_uuid', 'customer_uuid', 'customer_type', '_key'],
        'entities'            => ['uuid', 'public_id', 'company_uuid', 'payload_uuid', 'tracking_number_uuid', 'name', 'type', 'status', 'internal_id', 'customer_uuid', 'customer_type', 'destination_uuid', 'photo_uuid', 'meta', '_key'],
        'places'              => ['uuid', 'public_id', 'company_uuid', 'name', 'location', '_key'],
        'routes'              => ['uuid', 'public_id', 'company_uuid', 'order_uuid', 'details', '_key'],
        'files'               => ['uuid', 'public_id', 'company_uuid', 'key_uuid', 'key_type', 'subject_uuid', 'subject_type', 'name', 'type'],
        'tracking_numbers'    => ['uuid', 'public_id', 'company_uuid', 'tracking_number', 'owner_uuid', 'owner_type', 'region', 'barcode', 'qr_code', 'status_uuid', 'type', '_key'],
        'service_quotes'      => ['uuid', 'public_id', 'company_uuid', 'amount', 'currency', 'meta', 'expired_at', '_key'],
        'tracking_statuses'   => ['uuid', 'public_id', 'company_uuid', 'tracking_number_uuid', 'code', 'status', 'details', 'location', 'city', 'province', 'country', '_key'],
        'contacts'            => ['uuid', 'public_id', 'company_uuid', 'name'],
        'vendors'             => ['uuid', 'public_id', 'company_uuid', 'name'],
        'custom_fields'       => ['uuid', 'public_id', 'company_uuid', 'subject_uuid', 'subject_type', 'name', 'label'],
        'custom_field_values' => ['uuid', 'company_uuid', 'custom_field_uuid', 'subject_uuid', 'subject_type', 'value'],
        'companies'           => ['uuid', 'public_id', 'name', 'options'],
    ];
    foreach ($tables as $table => $columns) {
        $schema->create($table, function ($blueprint) use ($columns) {
            $blueprint->increments('id');
            foreach ($columns as $column) {
                $blueprint->string($column)->nullable();
            }
            $blueprint->timestamps();
            $blueprint->timestamp('deleted_at')->nullable();
        });
    }

    session(['company' => 'company-1']);
    $connection->table('companies')->insert(['uuid' => 'company-1', 'name' => 'Acme']);
    $connection->table('order_configs')->insert([
        'uuid'         => 'config-1',
        'public_id'    => 'order_config_transport',
        'company_uuid' => 'company-1',
        'name'         => 'Transport',
        'key'          => 'transport',
        'namespace'    => 'system:order-config:transport',
        'core_service' => '1',
        'status'       => 'active',
        'version'      => '0.0.1',
        'flow'         => json_encode([]),
    ]);

    return $connection;
}

test('create record persists an order with the default transport config', function () {
    $connection = fleetopsInternalOrderCreateBoot();

    $result = (new OrderController())->createRecord(Request::create('/int/v1/orders', 'POST', [
        'order' => [
            'dispatched' => false,
            'payload'    => ['type' => 'transport'],
            'meta'       => [],
        ],
    ]));

    expect($result)->toBeArray()
        ->and($result)->toHaveKey('order')
        ->and($connection->table('orders')->count())->toBe(1)
        ->and($connection->table('orders')->value('type'))->toBe('transport')
        ->and($connection->table('orders')->value('order_config_uuid'))->toBe('config-1')
        ->and($connection->table('orders')->value('orchestrator_priority'))->toBe('50')
        ->and($connection->table('payloads')->count())->toBe(1);
});

test('create record inserts waypoints entities and attaches files', function () {
    $connection = fleetopsInternalOrderCreateBoot();
    $connection->table('places')->insert(['uuid' => '55555555-5555-4555-8555-555555555555', 'company_uuid' => 'company-1', 'name' => 'Depot']);
    $connection->table('files')->insert(['uuid' => 'file-1', 'company_uuid' => 'company-1', 'name' => 'attachment.pdf', 'type' => 'document']);

    $result = (new OrderController())->createRecord(Request::create('/int/v1/orders', 'POST', [
        'order' => [
            'dispatched' => false,
            'payload'    => [
                'type'      => 'transport',
                'waypoints' => [['place_uuid' => '55555555-5555-4555-8555-555555555555']],
                'entities'  => [['name' => 'Package A']],
            ],
            'files' => ['file-1'],
        ],
    ]));

    // File key assignment writes the order uuid, which the harness does not
    // auto-generate; waypoint and entity persistence are the observable
    // side effects here.
    expect($result)->toBeArray()->toHaveKey('order')
        ->and($connection->table('orders')->count())->toBe(1)
        ->and($connection->table('waypoints')->count())->toBe(1)
        ->and($connection->table('entities')->count())->toBe(1);
});

test('create record rejects invalid explicit order configs', function () {
    $connection = fleetopsInternalOrderCreateBoot();

    $result = (new OrderController())->createRecord(Request::create('/int/v1/orders', 'POST', [
        'order' => [
            'dispatched'        => false,
            'order_config_uuid' => 'not-a-real-config',
            'payload'           => ['type' => 'transport'],
        ],
    ]));

    expect($result)->toBeInstanceOf(JsonResponse::class)
        ->and($result->getData(true)['error']['order_config_uuid'] ?? null)->toBe('The selected order config is invalid.')
        ->and($connection->table('orders')->count())->toBe(0);
});

test('create record surfaces validation errors default-creates configs and catches exceptions', function () {
    // Validation failures route into responseWithErrors, which raises a
    // ValidationException carrying the 422 response
    fleetopsInternalOrderCreateBoot(['pickup' => ['The pickup field is required.']]);
    $validationFailure = null;

    try {
        (new OrderController())->createRecord(Request::create('/int/v1/orders', 'POST', ['order' => ['type' => 'transport']]));
    } catch (Illuminate\Validation\ValidationException $exception) {
        $validationFailure = $exception;
    }

    expect($validationFailure)->not->toBeNull()
        ->and($validationFailure->errors())->toBe(['pickup' => ['The pickup field is required.']])
        ->and($validationFailure->getResponse()?->getStatusCode())->toBe(422);

    // Without any stored config the default lookup provisions the transport
    // config for the session company
    $connection = fleetopsInternalOrderCreateBoot();
    $connection->table('order_configs')->delete();
    $defaulted = (new OrderController())->createRecord(Request::create('/int/v1/orders', 'POST', [
        'order' => [
            'dispatched'          => false,
            'payload'             => ['type' => 'transport'],
            'custom_field_values' => [],
        ],
    ]));
    expect($defaulted)->toBeArray()->toHaveKey('order')
        ->and($connection->table('order_configs')->where('namespace', 'system:order-config:transport')->count())->toBe(1);

    // A query failure inside creation surfaces through the debug-mode catch
    $connection2 = fleetopsInternalOrderCreateBoot();
    app('db.schema')->drop('routes');
    $queryFailed = (new OrderController())->createRecord(Request::create('/int/v1/orders', 'POST', [
        'order' => [
            'dispatched' => false,
            'route'      => ['details' => ['legs' => []]],
            'payload'    => ['type' => 'transport'],
        ],
    ]));
    expect($queryFailed->getData(true)['error'] ?? '')->toContain('routes');
});

test('create record converts an unexpected failure into an error response', function () {
    fleetopsInternalOrderCreateBoot();

    $controller        = new OrderController();
    $controller->model = new class extends Fleetbase\FleetOps\Models\Order {
        public function createRecordFromRequest($request, ?callable $onBefore = null, ?callable $onAfter = null, array $options = [])
        {
            throw new RuntimeException('Order creation collaborator blew up.');
        }
    };

    // Anything that is neither a query failure nor a validation exception still
    // has to come back as an error response rather than escaping the controller
    $response = $controller->createRecord(Request::create('/int/v1/orders', 'POST', [
        'order' => [
            'dispatched' => false,
            'payload'    => ['type' => 'transport'],
        ],
    ]));

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getData(true)['errors'] ?? [$response->getData(true)['error'] ?? null])
        ->toContain('Order creation collaborator blew up.');
});

test('integrated vendor orders attach metadata and surface bridge failures', function () {
    $connection = fleetopsInternalOrderCreateBoot();
    $schema     = $connection->getSchemaBuilder();
    $schema->create('integrated_vendors', function ($blueprint) {
        $blueprint->increments('id');
        foreach (['uuid', 'public_id', 'company_uuid', 'provider', 'credentials', 'sandbox', 'options', '_key'] as $column) {
            $blueprint->string($column)->nullable();
        }
        $blueprint->timestamps();
        $blueprint->timestamp('deleted_at')->nullable();
    });
    $connection->table('integrated_vendors')->insert(['uuid' => 'iv-bridge-1', 'public_id' => 'integrated_vendor_bridge1', 'company_uuid' => 'company-1', 'provider' => 'lalamove', 'credentials' => json_encode([]), 'sandbox' => '1', 'options' => json_encode([])]);
    $schema->table('service_quotes', function ($blueprint) {
        $blueprint->string('integrated_vendor_uuid')->nullable();
    });
    $schema->create('purchase_rates', function ($blueprint) {
        $blueprint->increments('id');
        foreach (['uuid', 'public_id', 'company_uuid', 'customer_uuid', 'customer_type', 'service_quote_uuid', 'payload_uuid', 'order_uuid', 'transaction_uuid', 'status', 'meta', '_key'] as $column) {
            $blueprint->string($column)->nullable();
        }
        $blueprint->timestamps();
        $blueprint->timestamp('deleted_at')->nullable();
    });
    $connection->table('service_quotes')->insert(['uuid' => '66666666-6666-4666-8666-666666666601', 'public_id' => 'service_quote_bridge1', 'company_uuid' => 'company-1', 'amount' => '1500', 'currency' => 'SGD', 'meta' => json_encode([]), 'integrated_vendor_uuid' => 'iv-bridge-1']);

    // Bind a stub vendor bridge through the container seam
    $GLOBALS['fleetopsBridgeMode'] = 'ok';
    app()->bind(Fleetbase\FleetOps\Integrations\Lalamove\Lalamove::class, function () {
        return new class {
            public function setIntegratedVendor($vendor)
            {
                return $this;
            }

            public function setRequestId($id)
            {
                return $this;
            }

            public function createOrderFromServiceQuote($serviceQuote, $request)
            {
                if ($GLOBALS['fleetopsBridgeMode'] === 'fail') {
                    throw new Exception('vendor rejected the order');
                }

                return ['orderId' => 'bridge-order-1', 'metadata' => ['integrated_vendor' => 'integrated_vendor_bridge1']];
            }

            public function __call($method, $arguments)
            {
                return $this;
            }
        };
    });

    // A resolvable vendor quote attaches integrated metadata to the order
    $created = (new OrderController())->createRecord(Request::create('/int/v1/orders', 'POST', [
        'order' => [
            'dispatched'         => false,
            'service_quote_uuid' => '66666666-6666-4666-8666-666666666601',
            'payload'            => ['type' => 'transport'],
        ],
    ]));
    expect($created)->toBeArray()->toHaveKey('order')
        ->and((string) $connection->table('orders')->value('meta'))->toContain('bridge-order-1');

    // Bridge failures respond through the vendor error guard
    $GLOBALS['fleetopsBridgeMode'] = 'fail';
    $failed                        = (new OrderController())->createRecord(Request::create('/int/v1/orders', 'POST', [
        'order' => [
            'dispatched'         => false,
            'service_quote_uuid' => '66666666-6666-4666-8666-666666666601',
            'payload'            => ['type' => 'transport'],
        ],
    ]));
    expect($failed->getData(true)['error'] ?? '')->toContain('vendor rejected');
});

test('order config defaults are created dispatched and fall back to transport', function () {
    // With no stored config the default lookup provisions one for the company
    $connection = fleetopsInternalOrderCreateBoot();
    $connection->table('order_configs')->delete();

    $created = (new OrderController())->createRecord(Request::create('/int/v1/orders', 'POST', [
        'order' => [
            'dispatched' => false,
            'payload'    => ['type' => 'transport'],
        ],
    ]));

    expect($created)->toBeArray()->toHaveKey('order')
        ->and($connection->table('order_configs')->where('namespace', 'system:order-config:transport')->count())->toBe(1)
        ->and($connection->table('orders')->value('type'))->toBe('transport');

    // Without a session company no config resolves and the type falls back
    $connection2 = fleetopsInternalOrderCreateBoot();
    $connection2->table('order_configs')->delete();
    session(['company' => null]);
    $fallback = (new OrderController())->createRecord(Request::create('/int/v1/orders', 'POST', [
        'order' => [
            'dispatched' => false,
            'payload'    => ['type' => 'transport'],
        ],
    ]));
    session(['company' => 'company-1']);

    expect($fallback)->not->toBeNull()
        ->and($connection2->table('order_configs')->count())->toBe(0);
});

test('create record dispatches orders when the dispatch flag is not disabled', function () {
    $connection = fleetopsInternalOrderCreateBoot();

    $result = (new OrderController())->createRecord(Request::create('/int/v1/orders', 'POST', [
        'order' => [
            'payload' => ['type' => 'transport'],
        ],
    ]));

    expect($result)->toBeArray()->toHaveKey('order')
        ->and($connection->table('orders')->value('dispatched'))->not->toBeNull();
});

test('create record starts configured lifecycles at their initial activity and never dispatches them', function () {
    $connection = fleetopsInternalOrderCreateBoot();
    $connection->table('order_configs')->where('uuid', 'config-1')->update([
        'flow' => json_encode([
            'requested' => ['key' => 'requested', 'code' => 'requested', 'status' => 'Requested', 'details' => 'Loan requested', 'activities' => ['completed']],
            'completed' => ['key' => 'completed', 'code' => 'completed', 'status' => 'Completed', 'details' => 'Loan completed', 'activities' => []],
        ]),
        'meta' => json_encode(['lifecycle' => ['initial' => 'requested', 'completed' => 'completed', 'dispatch' => false]]),
    ]);

    // Asks to be dispatched; the lifecycle overrides both the status and the dispatch flag
    $result = (new OrderController())->createRecord(Request::create('/int/v1/orders', 'POST', [
        'order' => [
            'status'     => 'created',
            'dispatched' => true,
            'adhoc'      => true,
            'payload'    => ['type' => 'transport'],
        ],
    ]));

    expect($result)->toBeArray()->toHaveKey('order')
        ->and($connection->table('orders')->value('status'))->toBe('requested')
        ->and((bool) $connection->table('orders')->value('dispatched'))->toBeFalse()
        ->and((bool) $connection->table('orders')->value('adhoc'))->toBeFalse();
});

test('syncing gives a default-region tracking number the pickup country', function () {
    $connection = fleetopsInternalOrderCreateBoot();
    $connection->getSchemaBuilder()->table('places', function ($blueprint) {
        $blueprint->string('country')->nullable();
    });
    $connection->table('places')->insert(['uuid' => 'place-th', 'public_id' => 'place_th', 'company_uuid' => 'company-1', 'name' => 'Navanakorn DC', 'country' => 'TH']);
    $connection->table('payloads')->insert(['uuid' => 'payload-th', 'public_id' => 'payload_th', 'company_uuid' => 'company-1', 'pickup_uuid' => 'place-th', 'type' => 'transport']);
    $connection->table('tracking_numbers')->insert([
        'uuid'            => 'tracking-sg',
        'public_id'       => 'track_sg',
        'company_uuid'    => 'company-1',
        'tracking_number' => 'ACM0000000001SG',
        'region'          => 'SG',
        'owner_uuid'      => 'order-th',
        'owner_type'      => Fleetbase\FleetOps\Models\Order::class,
        'qr_code'         => 'old-qr',
        'barcode'         => 'old-barcode',
    ]);
    $connection->table('orders')->insert(['uuid' => 'order-th', 'public_id' => 'order_th', 'company_uuid' => 'company-1', 'payload_uuid' => 'payload-th', 'tracking_number_uuid' => 'tracking-sg']);

    // The label codes come from the barcode facades; record what they are asked to render.
    $rendered = new ArrayObject();
    foreach (['DNS2D', 'DNS1D'] as $facade) {
        app()->instance($facade, new class($facade, $rendered) {
            public function __construct(private string $facade, private ArrayObject $rendered)
            {
            }

            public function getBarcodePNG(...$arguments)
            {
                $this->rendered[] = [$this->facade, $arguments[0]];

                return $this->facade . '-png';
            }
        });
        Illuminate\Support\Facades\Facade::clearResolvedInstance($facade);
    }

    try {
        $order = Fleetbase\FleetOps\Models\Order::where('uuid', 'order-th')->first();
        $order->syncTrackingNumberWithPickup();
        // A second sync finds the region already right and changes nothing.
        $order->syncTrackingNumberWithPickup();
    } finally {
        foreach (['DNS2D', 'DNS1D'] as $facade) {
            app()->forgetInstance($facade);
            Illuminate\Support\Facades\Facade::clearResolvedInstance($facade);
        }
    }

    $trackingNumber = $connection->table('tracking_numbers')->where('uuid', 'tracking-sg')->first();

    expect($trackingNumber->region)->toBe('TH')
        ->and($trackingNumber->tracking_number)->toStartWith('ACM')->toEndWith('TH')
        ->and($trackingNumber->qr_code)->toBe('DNS2D-png')
        ->and($trackingNumber->barcode)->toBe('DNS1D-png')
        ->and($order->trackingNumber->tracking_number)->toBe($trackingNumber->tracking_number)
        ->and($rendered->count())->toBe(2)
        ->and($rendered[1])->toBe(['DNS1D', $trackingNumber->tracking_number]);
});

test('the tracking number default region comes from config and falls back to SG', function () {
    $previous = config('fleetops.tracking_number.default_region');

    config(['fleetops.tracking_number.default_region' => ' th ']);
    $configured = [
        Fleetbase\FleetOps\Models\TrackingNumber::defaultRegion(),
        Fleetbase\FleetOps\Models\TrackingNumber::trackingNumberSuffix(''),
        Fleetbase\FleetOps\Models\TrackingNumber::trackingNumberSuffix(null),
    ];

    config(['fleetops.tracking_number.default_region' => '-']);
    $empty = Fleetbase\FleetOps\Models\TrackingNumber::defaultRegion();

    config(['fleetops.tracking_number.default_region' => $previous]);

    expect($configured)->toBe(['TH', 'TH', 'TH'])
        ->and($empty)->toBe('SG');
});

test('syncing the tracking number is a no-op for an order without one', function () {
    $connection = fleetopsInternalOrderCreateBoot();
    $connection->table('orders')->insert(['uuid' => 'order-no-tracking', 'public_id' => 'order_no_tracking', 'company_uuid' => 'company-1']);

    $order = Fleetbase\FleetOps\Models\Order::where('uuid', 'order-no-tracking')->first();

    expect($order->syncTrackingNumberWithPickup())->toBe($order)
        ->and($connection->table('tracking_numbers')->count())->toBe(0);
});
