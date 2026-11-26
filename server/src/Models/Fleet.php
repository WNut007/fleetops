<?php

namespace Fleetbase\FleetOps\Models;

use Fleetbase\FleetOps\Support\Utils;
use Fleetbase\Models\Model;
use Fleetbase\Traits\HasApiModelBehavior;
use Fleetbase\Traits\HasCustomFields;
use Fleetbase\Traits\HasPublicId;
use Fleetbase\Traits\HasUuid;
use Fleetbase\Traits\Searchable;
use Fleetbase\Traits\SendsWebhooks;
use Fleetbase\Traits\TracksApiCredential;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Fleet extends Model
{
    use HasUuid;
    use HasPublicId;
    use HasApiModelBehavior;
    use TracksApiCredential;
    use SendsWebhooks;
    use Searchable;
    use HasSlug;
    use LogsActivity;
    use Notifiable;
    use HasCustomFields;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'fleets';

    /**
     * The type of public Id to generate.
     *
     * @var string
     */
    protected $publicIdType = 'fleet';

    /**
     * These attributes that can be queried.
     *
     * @var array
     */
    protected $searchableColumns = ['name'];

    /**
     * Get the activity log options for the model.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'task', 'service_area_uuid', 'zone_uuid'])->logOnlyDirty();
    }

    /**
     * The flug options for this model.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        '_key',
        'public_id',
        'company_uuid',
        'service_area_uuid',
        'zone_uuid',
        'vendor_uuid',
        'parent_fleet_uuid',
        'image_uuid',
        'name',
        'color',
        'task',
        'status',
        'slug',
    ];

    /**
     * Dynamic attributes that are appended to object.
     *
     * @var array
     */
    protected $appends = ['photo_url', 'drivers_count', 'drivers_online_count', 'vehicles_count', 'vehicles_online_count'];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [];

    public function photo(): BelongsTo
    {
        return $this->belongsTo(\Fleetbase\Models\File::class, 'image_uuid', 'uuid');
    }

    public function serviceArea(): BelongsTo
    {
        return $this->belongsTo(ServiceArea::class)->select(['uuid', 'public_id', 'name', 'type', 'border']);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class)->select(['uuid', 'public_id', 'name', 'border']);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class)->select(['uuid', 'public_id', 'name']);
    }

    public function parentFleet(): BelongsTo
    {
        return $this->belongsTo(Fleet::class)->without(['subFleets']);
    }

    public function subFleets(): HasMany
    {
        return $this->hasMany(Fleet::class, 'parent_fleet_uuid', 'uuid')->without(['parentFleet']);
    }

    public function drivers(): HasManyThrough
    {
        return $this->hasManyThrough(Driver::class, FleetDriver::class, 'fleet_uuid', 'uuid', 'uuid', 'driver_uuid');
    }

    public function vehicles(): HasManyThrough
    {
        return $this->hasManyThrough(Vehicle::class, FleetVehicle::class, 'fleet_uuid', 'uuid', 'uuid', 'vehicle_uuid');
    }

    /**
     * Get avatar URL attribute.
     *
     * @return string
     */
    public function getPhotoUrlAttribute()
    {
        return data_get($this, 'photo.url', 'https://s3.ap-northeast-2.amazonaws.com/fleetbase/public/default-fleet.png');
    }

    /**
     * Get the number of drivers in fleet.
     *
     * @return int
     */
    public function getDriversCountAttribute()
    {
        // Counted for the whole page by the list query (loadCount) when it ran.
        if (array_key_exists('drivers_count', $this->attributes)) {
            return (int) $this->attributes['drivers_count'];
        }

        // Count the loaded relation when there is one: these are read for every fleet in a list.
        if ($this->relationLoaded('drivers')) {
            return $this->drivers->count();
        }

        return $this->drivers()->count();
    }

    /**
     * Get the number of online drivers in fleet.
     *
     * @return int
     */
    public function getDriversOnlineCountAttribute()
    {
        // Counted for the whole page by the list query (loadCount) when it ran.
        if (array_key_exists('drivers_online_count', $this->attributes)) {
            return (int) $this->attributes['drivers_online_count'];
        }

        // Count the loaded relation when there is one: these are read for every fleet in a list.
        if ($this->relationLoaded('drivers')) {
            return $this->drivers->where('online', 1)->count();
        }

        return $this->drivers()->where('online', 1)->count();
    }

    /**
     * Get the number of vehicles in fleet.
     *
     * @return int
     */
    public function getVehiclesCountAttribute()
    {
        // Counted for the whole page by the list query (loadCount) when it ran.
        if (array_key_exists('vehicles_count', $this->attributes)) {
            return (int) $this->attributes['vehicles_count'];
        }

        // Count the loaded relation when there is one: these are read for every fleet in a list.
        if ($this->relationLoaded('vehicles')) {
            return $this->vehicles->count();
        }

        return $this->vehicles()->count();
    }

    /**
     * Get the number of online vehicles in fleet.
     *
     * @return int
     */
    public function getVehiclesOnlineCountAttribute()
    {
        if (array_key_exists('vehicles_online_count', $this->attributes)) {
            return (int) $this->attributes['vehicles_online_count'];
        }

        // Count the loaded relation when there is one: these are read for every fleet in a list.
        if ($this->relationLoaded('vehicles')) {
            return $this->vehicles->where('online', 1)->count();
        }

        return $this->vehicles()->where('online', 1)->count();
    }

    public static function createFromImport(array $row, bool $saveInstance = false): Fleet
    {
        // Filter array for null key values
        $row = array_filter($row);

        // Get fleet columns
        $name  = Utils::or($row, ['name', 'fleet', 'fleet_name']);

        // Create fleet
        $fleet = new static([
            'company_uuid' => session('company'),
            'name'         => $name,
            'status'       => 'active',
        ]);

        if ($saveInstance === true) {
            $fleet->save();
        }

        return $fleet;
    }
}
