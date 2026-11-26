<?php

namespace Fleetbase\FleetOps\Http\Resources\v1;

use Fleetbase\FleetOps\Support\ProfileAccountManager;
use Fleetbase\FleetOps\Support\Utils;
use Fleetbase\Http\Resources\FleetbaseResource;
use Fleetbase\Http\Resources\User;
use Fleetbase\Support\Http;
use Illuminate\Support\Str;

class Contact extends FleetbaseResource
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
        $this->loadMissing(['place', 'places']);

        return $this->withCustomFields([
            'id'                            => $this->when(Http::isInternalRequest(), $this->id, $this->public_id),
            'customer_id'                   => $this->when($this->type === 'customer' && Http::isPublicRequest(), Str::replace('contact', 'customer', $this->public_id)),
            'uuid'                          => $this->when(Http::isInternalRequest(), $this->uuid),
            'company_uuid'                  => $this->when(Http::isInternalRequest(), $this->company_uuid),
            'user_uuid'                     => $this->when(Http::isInternalRequest(), $this->user_uuid),
            'place_uuid'                    => $this->when(Http::isInternalRequest(), $this->place_uuid),
            'photo_uuid'                    => $this->when(Http::isInternalRequest(), $this->photo_uuid),
            'public_id'                     => $this->when(Http::isInternalRequest(), $this->public_id),
            'internal_id'                   => $this->internal_id,
            'name'                          => $this->name,
            'title'                         => $this->title ?? null,
            'email'                         => $this->email ?? null,
            'phone'                         => $this->phone ?? null,
            'photo_url'                     => $this->photo_url ?? null,
            'place'                         => $this->whenLoaded('place', fn () => (new Place($this->place))->without('owner')),
            'places'                        => $this->whenLoaded('places', fn () => Place::collection($this->places)->without('owner')),
            'user'                          => $this->when(Http::isInternalRequest(), fn () => static::isListRequest($request) ? static::listUser($this->user) : new User($this->user), fn () => $this->user ? $this->user->public_id : null),
            'address'                       => $this->when(Http::isInternalRequest(), data_get($this, 'place.address')),
            'is_staff_linked'               => $this->when(Http::isInternalRequest(), fn () => ProfileAccountManager::isStaffAccount($this->user)),
            'login_status'                  => $this->when(Http::isInternalRequest(), fn () => data_get($this, 'user.status')),
            'address_street'                => $this->when(Http::isInternalRequest(), data_get($this, 'place.street1')),
            'type'                          => $this->type ?? null,
            'customer_type'                 => $this->when(isset($this->customer_type), Utils::toEmberResourceType($this->customer_type)),
            'facilitator_type'              => $this->when(isset($this->facilitator_type), Utils::toEmberResourceType($this->facilitator_type)),
            'meta'                          => data_get($this, 'meta', Utils::createObject()),
            'slug'                          => $this->slug ?? null,
            'updated_at'                    => $this->updated_at,
            'created_at'                    => $this->created_at,
        ]);
    }

    /**
     * Transform the resource into an webhook payload.
     *
     * @return array
     */
    public function toWebhookPayload()
    {
        return [
            'id'          => $this->public_id,
            'internal_id' => $this->internal_id,
            'name'        => $this->name,
            'title'       => $this->title ?? null,
            'email'       => $this->email ?? null,
            'phone'       => $this->phone ?? null,
            'photo_url'   => $this->photo_url ?? null,
            'type'        => $this->type ?? null,
            'meta'        => data_get($this, 'meta', Utils::createObject()),
            'slug'        => $this->slug ?? null,
            'updated_at'  => $this->updated_at,
            'created_at'  => $this->created_at,
        ];
    }

    /**
     * A GET for many records (no route parameter): lists, and other lists embedding contacts.
     */
    protected static function isListRequest($request): bool
    {
        $route = $request?->route();

        return $request && $request->isMethod('GET') && (!$route || empty($route->parameters()));
    }

    /**
     * The login account of a contact in a list, with its plain columns but without role, policies,
     * permissions and the other per-user lookups (locale, company membership): the full
     * User resource cost ~10 queries per row (role, policies, permissions, company membership),
     * 300 of the 318 queries of a 30-row customer list. Opening one contact returns the full user.
     */
    protected static function listUser($user): ?array
    {
        if (!$user) {
            return null;
        }

        return [
            'id'                => $user->id,
            'uuid'              => $user->uuid,
            'public_id'         => $user->public_id,
            'company_uuid'      => $user->company_uuid,
            'name'              => $user->name,
            'username'          => $user->username,
            'email'             => $user->email,
            'phone'             => $user->phone,
            'country'           => $user->country,
            'timezone'          => $user->timezone,
            'date_of_birth'     => $user->date_of_birth,
            'ip_address'        => $user->ip_address,
            'slug'              => $user->slug,
            'avatar_url'        => $user->avatar_url,
            'type'              => $user->type,
            'status'            => $user->status,
            'email_verified_at' => $user->email_verified_at,
            'phone_verified_at' => $user->phone_verified_at,
            'last_login'        => $user->last_login,
            'last_seen_at'      => $user->last_seen_at,
            'meta'              => data_get($user, 'meta', Utils::createObject()),
            'updated_at'        => $user->updated_at,
            'created_at'        => $user->created_at,
        ];
    }
}
