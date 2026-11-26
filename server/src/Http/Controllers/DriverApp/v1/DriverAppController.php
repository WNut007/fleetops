<?php

namespace Fleetbase\FleetOps\Http\Controllers\DriverApp\v1;

use Fleetbase\FleetOps\Http\Controllers\Api\v1\DriverController as ApiDriverController;
use Fleetbase\FleetOps\Http\Controllers\Api\v1\OrderController as ApiOrderController;
use Fleetbase\FleetOps\Http\Middleware\AuthenticateDriverApp;
use Fleetbase\FleetOps\Http\Resources\v1\Driver as DriverResource;
use Fleetbase\FleetOps\Http\Resources\v1\Order as OrderResource;
use Fleetbase\FleetOps\Models\Driver;
use Fleetbase\FleetOps\Models\Order;
use Fleetbase\FleetOps\Models\Position;
use Fleetbase\FleetOps\Support\DriverAppToken;
use Fleetbase\FleetOps\Support\MailText;
use Fleetbase\LaravelMysqlSpatial\Types\Point;
use Fleetbase\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The ShipCode driver app API (/driver/v1): a driver's own profile, jobs, status updates, proof of
 * delivery and GPS, and nothing of anyone else's.
 *
 * Every order route resolves the order among the signed-in driver's own assigned orders first and
 * answers 404 otherwise (an order of another driver is indistinguishable from a missing one). The
 * work itself is then done by the public API controllers, so status flow, proof handling, tracking,
 * notifications and events stay identical to the rest of the platform.
 */
class DriverAppController extends Controller
{
    /** Unfinished / finished order statuses for the job list scopes. */
    protected const FINISHED_STATUSES = ['completed', 'canceled'];

    protected const MESSAGES = [
        'en' => [
            'login_failed'     => 'The email/phone or password is incorrect.',
            'login_disabled'   => 'This driver login has been deactivated.',
            'no_profile'       => 'No driver profile is linked to this account.',
            'not_found'        => 'Order not found.',
            'activity_unknown' => 'This step is not available for the order right now.',
            'bad_location'     => 'The location is not valid.',
            'too_many_points'  => 'Send at most :max locations at a time.',
        ],
        'th' => [
            'login_failed'     => 'อีเมล/เบอร์โทร หรือรหัสผ่านไม่ถูกต้อง',
            'login_disabled'   => 'บัญชีคนขับนี้ถูกระงับการเข้าใช้งาน',
            'no_profile'       => 'บัญชีนี้ไม่ได้ผูกกับข้อมูลคนขับ',
            'not_found'        => 'ไม่พบออเดอร์',
            'activity_unknown' => 'ขั้นตอนนี้ยังทำไม่ได้สำหรับออเดอร์นี้',
            'bad_location'     => 'ตำแหน่งไม่ถูกต้อง',
            'too_many_points'  => 'ส่งตำแหน่งได้ครั้งละไม่เกิน :max จุด',
        ],
    ];

    public const MAX_BATCH_LOCATIONS = 500;

    // -----------------------------------------------------------------------------------------
    // Authentication
    // -----------------------------------------------------------------------------------------

    /**
     * POST auth/login {identity, password, device_name?} → {token, expires_at, driver}
     */
    public function login(Request $request): JsonResponse
    {
        $identity = trim((string) $request->input('identity'));
        $password = (string) $request->input('password');
        $locale   = $this->requestLocale($request);

        $user = $identity === '' ? null : User::where(function ($query) use ($identity) {
            $query->where('email', $identity)->orWhere('phone', static::phone($identity));
        })->whereHas('driver')->first();

        if (!$user || !is_string($user->password) || !Hash::check($password, $user->password)) {
            return $this->error('login_failed', 401, $locale);
        }

        if (ApiDriverController::isLoginDeactivated($user)) {
            return $this->error('login_disabled', 403, $locale);
        }

        // The profile in the user's current company, else their first one
        $profiles = Driver::where('user_uuid', $user->uuid)->orderBy('id')->get();
        $driver   = $profiles->firstWhere('company_uuid', $user->company_uuid) ?? $profiles->first();
        if (!$driver) {
            return $this->error('no_profile', 403, $locale);
        }

        $expiresAt = now()->addDays(DriverAppToken::TTL_DAYS);
        $token     = $user->createToken(DriverAppToken::nameFor($driver->uuid), [DriverAppToken::ABILITY], $expiresAt);

        return response()->json([
            'token'      => $token->plainTextToken,
            'expires_at' => $expiresAt->toIso8601String(),
            'driver'     => (new DriverResource($driver))->resolve($request),
        ]);
    }

    /**
     * POST auth/logout: revokes the token of this device.
     */
    public function logout(Request $request): JsonResponse
    {
        $token = DriverAppToken::find($request->bearerToken());
        if ($token instanceof PersonalAccessToken && DriverAppToken::isDriverAppToken($token)) {
            $token->delete();
        }

        return response()->json(['status' => 'ok']);
    }

    // -----------------------------------------------------------------------------------------
    // The driver
    // -----------------------------------------------------------------------------------------

    public function me(Request $request)
    {
        return new DriverResource($this->driver($request)->loadMissing(['vehicle', 'user']));
    }

    /**
     * POST me/online {online?}: sets (or toggles) the driver's and their vehicle's online flag.
     */
    public function online(Request $request)
    {
        return app(ApiDriverController::class)->toggleOnline($this->driver($request)->public_id, $request);
    }

    /**
     * POST me/device {token, platform}: registers the device for push notifications.
     */
    public function device(Request $request)
    {
        return app(ApiDriverController::class)->registerDevice($this->driver($request)->public_id, $request);
    }

    // -----------------------------------------------------------------------------------------
    // Jobs
    // -----------------------------------------------------------------------------------------

    /**
     * GET orders?scope=active|history&page=&limit= — the driver's own assigned orders.
     * active: unfinished, soonest scheduled first; history: finished, newest first.
     */
    public function orders(Request $request)
    {
        $driver = $this->driver($request);
        $limit  = max(1, min(50, (int) $request->input('limit', 20)));
        $scope  = $request->input('scope', 'active') === 'history' ? 'history' : 'active';

        $query = Order::where('company_uuid', $driver->company_uuid)
            ->where('driver_assigned_uuid', $driver->uuid)
            ->whereNotNull('payload_uuid')
            ->with(['trackingNumber', 'trackingStatuses', 'driverAssigned', 'vehicleAssigned', 'customer', 'facilitator']);

        if ($scope === 'history') {
            $query->whereIn('status', static::FINISHED_STATUSES)->orderByDesc('updated_at')->orderByDesc('id');
        } else {
            $query->whereNotIn('status', static::FINISHED_STATUSES)
                ->orderByRaw('scheduled_at is null')
                ->orderBy('scheduled_at')
                ->orderByDesc('created_at')
                ->orderByDesc('id');
        }

        return OrderResource::collection($query->paginate($limit));
    }

    public function order(Request $request, string $id)
    {
        if (!$order = $this->ownOrder($request, $id)) {
            return $this->notFound($request);
        }

        return app(ApiOrderController::class)->find($order->public_id, $request);
    }

    /**
     * GET orders/{id}/next — the steps the driver can take next (code, status, require_pod…).
     */
    public function next(Request $request, string $id)
    {
        if (!$order = $this->ownOrder($request, $id)) {
            return $this->notFound($request);
        }

        return app(ApiOrderController::class)->getNextActivity($order->public_id, $request);
    }

    /**
     * POST orders/{id}/activity {code, proof_id?, waypoint?} — moves the order to the next step
     * named by its code. The step's definition comes from the order config (the next-step list),
     * never from the client, so the app cannot invent statuses or skip proof of delivery.
     */
    public function activity(Request $request, string $id)
    {
        $locale = $this->requestLocale($request);
        if (!$order = $this->ownOrder($request, $id)) {
            return $this->notFound($request);
        }

        $code = (string) $request->input('code');
        $next = app(ApiOrderController::class)->getNextActivity($order->public_id, $request);
        $list = $next instanceof JsonResponse ? $next->getData(true) : null;
        $list = is_array($list) && array_is_list($list) ? $list : [];
        $step = collect($list)->first(fn ($activity) => is_array($activity) && ($activity['code'] ?? null) === $code);

        if ($code === '' || !$step) {
            return $this->error('activity_unknown', 422, $locale);
        }

        $forward = static::forwardRequest($request, array_filter([
            'activity' => $step,
            'proof'    => $request->input('proof_id'),
            'waypoint' => $request->input('waypoint'),
        ], fn ($value) => $value !== null));

        return app(ApiOrderController::class)->updateActivity($order->public_id, $forward);
    }

    public function signature(Request $request, string $id, ?string $subjectId = null)
    {
        if (!$order = $this->ownOrder($request, $id)) {
            return $this->notFound($request);
        }

        return app(ApiOrderController::class)->captureSignature($request, $order->public_id, $subjectId);
    }

    public function photo(Request $request, string $id, ?string $subjectId = null)
    {
        if (!$order = $this->ownOrder($request, $id)) {
            return $this->notFound($request);
        }

        return app(ApiOrderController::class)->capturePhoto($request, $order->public_id, $subjectId);
    }

    public function qr(Request $request, string $id, ?string $subjectId = null)
    {
        if (!$order = $this->ownOrder($request, $id)) {
            return $this->notFound($request);
        }

        return app(ApiOrderController::class)->captureQrScan($request, $order->public_id, $subjectId);
    }

    // -----------------------------------------------------------------------------------------
    // GPS
    // -----------------------------------------------------------------------------------------

    /**
     * POST location {latitude, longitude, heading?, speed?, altitude?} — the live position: updates
     * the driver and vehicle, the live map, geofences and order triggers (same as /v1 track).
     */
    public function location(Request $request)
    {
        if (!static::validPoint($request->input('latitude'), $request->input('longitude'))) {
            return $this->error('bad_location', 422, $this->requestLocale($request));
        }

        return app(ApiDriverController::class)->track($this->driver($request)->public_id, $request);
    }

    /**
     * POST locations {locations: [{latitude, longitude, heading?, speed?, altitude?, recorded_at}]}
     * — points kept by the app while offline. All but the newest are stored as position history at
     * their recorded time; the newest goes through location() as the live position.
     * Invalid points (out of range, no time, in the future) are skipped and counted.
     */
    public function locations(Request $request)
    {
        $locale = $this->requestLocale($request);
        $driver = $this->driver($request);
        $points = $request->input('locations');

        if (!is_array($points) || $points === []) {
            return $this->error('bad_location', 422, $locale);
        }

        if (count($points) > static::MAX_BATCH_LOCATIONS) {
            return $this->error('too_many_points', 422, $locale, ['max' => static::MAX_BATCH_LOCATIONS]);
        }

        $valid = collect($points)
            ->filter(fn ($point) => is_array($point) && static::validPoint($point['latitude'] ?? null, $point['longitude'] ?? null))
            ->map(function ($point) {
                try {
                    $point['_at'] = Carbon::parse($point['recorded_at'] ?? null);
                } catch (\Throwable $e) {
                    return null;
                }

                return $point;
            })
            ->filter(fn ($point) => $point && !empty($point['recorded_at']) && $point['_at']->lte(now()->addMinutes(2)))
            ->sortBy(fn ($point) => $point['_at']->getTimestamp())
            ->values();

        $stored = 0;
        if ($valid->isNotEmpty()) {
            $driver->loadMissing('vehicle');
            $orderUuid = $driver->getCurrentOrder()?->uuid;

            foreach ($valid->slice(0, -1) as $point) {
                foreach (array_filter([$driver, $driver->vehicle]) as $subject) {
                    $position = Position::create([
                        'company_uuid' => $driver->company_uuid,
                        'subject_uuid' => $subject->uuid,
                        'subject_type' => $subject->getMorphClass(),
                        'order_uuid'   => $orderUuid,
                        'coordinates'  => new Point((float) $point['latitude'], (float) $point['longitude']),
                        'heading'      => $point['heading'] ?? null,
                        'speed'        => $point['speed'] ?? null,
                        'altitude'     => $point['altitude'] ?? null,
                    ]);
                    $position->forceFill(['created_at' => $point['_at'], 'updated_at' => $point['_at']])->saveQuietly();
                }
                $stored++;
            }

            $latest  = $valid->last();
            $forward = static::forwardRequest($request, array_intersect_key($latest, array_flip(['latitude', 'longitude', 'heading', 'speed', 'altitude'])));
            app(ApiDriverController::class)->track($driver->public_id, $forward);
            $stored++;
        }

        return response()->json([
            'accepted' => $stored,
            'skipped'  => count($points) - $stored,
        ]);
    }

    // -----------------------------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------------------------

    /**
     * A new request carrying only the given input, for handing work to the public API controllers.
     * Request::duplicate() would keep the original JSON body, and Laravel reads a JSON request's
     * input from that body, so the forwarded input was silently ignored for JSON clients.
     */
    protected static function forwardRequest(Request $request, array $input): Request
    {
        $forward = Request::create($request->getUri(), $request->getMethod(), $input, [], [], ['HTTP_ACCEPT' => 'application/json']);
        $forward->setUserResolver($request->getUserResolver());
        $forward->setRouteResolver($request->getRouteResolver());
        if ($request->hasSession()) {
            $forward->setLaravelSession($request->session());
        }

        return $forward;
    }

    protected function driver(Request $request): Driver
    {
        return $request->attributes->get(AuthenticateDriverApp::DRIVER_ATTRIBUTE);
    }

    /**
     * One of the signed-in driver's assigned orders, by public id, uuid or internal id; else null.
     */
    protected function ownOrder(Request $request, string $id): ?Order
    {
        $driver = $this->driver($request);

        return Order::where('company_uuid', $driver->company_uuid)
            ->where('driver_assigned_uuid', $driver->uuid)
            ->where(function ($query) use ($id) {
                $query->where('public_id', $id)->orWhere('uuid', $id);
            })
            ->first();
    }

    protected static function validPoint($latitude, $longitude): bool
    {
        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            return false;
        }

        $latitude  = (float) $latitude;
        $longitude = (float) $longitude;

        return $latitude >= -90 && $latitude <= 90 && $longitude >= -180 && $longitude <= 180
            && !($latitude == 0.0 && $longitude == 0.0);
    }

    protected static function phone(string $identity): string
    {
        $phone = preg_replace('/[\s\-()]/', '', $identity);
        if (str_starts_with($phone, '0') && strlen($phone) === 10) {
            // Thai local mobile number 0XXXXXXXXX → +66XXXXXXXXX
            return '+66' . substr($phone, 1);
        }

        return str_starts_with($phone, '+') ? $phone : '+' . $phone;
    }

    /**
     * The signed-in driver's language, else the Accept-Language header, else the platform default.
     */
    protected function requestLocale(Request $request): string
    {
        $driver = $request->attributes->get(AuthenticateDriverApp::DRIVER_ATTRIBUTE);
        if ($driver) {
            return MailText::localeFor($driver);
        }

        $header = strtolower(substr((string) $request->header('Accept-Language'), 0, 2));

        return isset(static::MESSAGES[$header]) ? $header : MailText::localeFor(null);
    }

    protected function notFound(Request $request): JsonResponse
    {
        return $this->error('not_found', 404, $this->requestLocale($request));
    }

    protected function error(string $key, int $status, string $locale, array $replace = []): JsonResponse
    {
        $message = static::MESSAGES[$locale][$key] ?? static::MESSAGES['en'][$key] ?? $key;
        foreach ($replace as $name => $value) {
            $message = str_replace(':' . $name, (string) $value, $message);
        }

        return response()->json(['errors' => [$message], 'code' => $key], $status);
    }
}
