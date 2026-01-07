<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;
use OffloadProject\Mandate\Contracts\Permission as PermissionContract;
use OffloadProject\Mandate\Exceptions\PermissionAlreadyExistsException;
use OffloadProject\Mandate\Exceptions\PermissionNotFoundException;
use OffloadProject\Mandate\Guard;
use OffloadProject\Mandate\MandateRegistrar;

/**
 * @property int|string $id
 * @property string $name
 * @property string $guard
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Permission extends Model implements PermissionContract
{
    /** @var list<string> */
    protected $fillable = [
        'name',
        'guard',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('mandate.tables.permissions', 'permissions'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function create(array $attributes): static
    {
        $guard = $attributes['guard'] ?? Guard::getDefaultName();

        // Check if permission already exists
        $existing = static::query()
            ->where('name', $attributes['name'])
            ->where('guard', $guard)
            ->first();

        if ($existing) {
            throw PermissionAlreadyExistsException::create($attributes['name'], $guard);
        }

        $attributes['guard'] = $guard;

        /** @var static $permission */
        $permission = static::query()->create($attributes);

        app(MandateRegistrar::class)->forgetCachedPermissions();

        return $permission;
    }

    /**
     * {@inheritdoc}
     */
    public static function findByName(string $name, ?string $guard = null): PermissionContract
    {
        $guard ??= Guard::getDefaultName();

        // Use registrar cache for efficient lookups
        $permission = app(MandateRegistrar::class)->getPermissionByName($name, $guard);

        if (! $permission) {
            throw PermissionNotFoundException::withName($name, $guard);
        }

        return $permission;
    }

    /**
     * {@inheritdoc}
     */
    public static function findById(int|string $id, ?string $guard = null): PermissionContract
    {
        $query = static::query()->where('id', $id);

        if ($guard !== null) {
            $query->where('guard', $guard);
        }

        /** @var static|null $permission */
        $permission = $query->first();

        if (! $permission) {
            throw PermissionNotFoundException::withId($id, $guard);
        }

        return $permission;
    }

    /**
     * {@inheritdoc}
     */
    public static function findOrCreate(string $name, ?string $guard = null): PermissionContract
    {
        $guard ??= Guard::getDefaultName();

        /** @var static|null $permission */
        $permission = static::query()
            ->where('name', $name)
            ->where('guard', $guard)
            ->first();

        if ($permission) {
            return $permission;
        }

        /** @var static $permission */
        $permission = static::query()->create([
            'name' => $name,
            'guard' => $guard,
        ]);

        app(MandateRegistrar::class)->forgetCachedPermissions();

        return $permission;
    }

    /**
     * {@inheritdoc}
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            config('mandate.models.role', Role::class),
            config('mandate.tables.permission_role', 'permission_role'),
            config('mandate.column_names.permission_id', 'permission_id'),
            config('mandate.column_names.role_id', 'role_id')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function subjects(): MorphToMany
    {
        // Get the model class for this permission's guard from auth config
        $modelClass = Guard::getModelClassForGuard($this->guard) ?? Model::class;

        return $this->morphedByMany(
            $modelClass,
            'subject',
            config('mandate.tables.permission_subject', 'permission_subject'),
            config('mandate.column_names.permission_id', 'permission_id'),
            config('mandate.column_names.subject_morph_key', 'subject_id')
        );
    }

    /**
     * Scope query to specific guard.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForGuard(Builder $query, string $guard): Builder
    {
        return $query->where('guard', $guard);
    }

    /**
     * Boot the model and register cache invalidation events.
     */
    protected static function booted(): void
    {
        static::saved(fn () => app(MandateRegistrar::class)->forgetCachedPermissions());
        static::deleted(fn () => app(MandateRegistrar::class)->forgetCachedPermissions());
    }
}
