<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use OffloadProject\Mandate\Contracts\Capability as CapabilityContract;
use OffloadProject\Mandate\Contracts\Permission as PermissionContract;
use OffloadProject\Mandate\Exceptions\CapabilityAlreadyExistsException;
use OffloadProject\Mandate\Exceptions\CapabilityNotFoundException;
use OffloadProject\Mandate\Guard;
use OffloadProject\Mandate\MandateRegistrar;

/**
 * @property int|string $id
 * @property string $name
 * @property string $guard
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Capability extends Model implements CapabilityContract
{
    /** @var list<string> */
    protected $fillable = [
        'name',
        'guard',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('mandate.tables.capabilities', 'capabilities'));

        $idType = config('mandate.model_id_type', 'int');
        if (in_array($idType, ['uuid', 'ulid'], true)) {
            $this->keyType = 'string';
            $this->incrementing = false;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function create(array $attributes): static
    {
        $guard = $attributes['guard'] ?? Guard::getDefaultName();

        // Check if capability already exists
        $existing = static::query()
            ->where('name', $attributes['name'])
            ->where('guard', $guard)
            ->first();

        if ($existing) {
            throw CapabilityAlreadyExistsException::create($attributes['name'], $guard);
        }

        $attributes['guard'] = $guard;

        /** @var static $capability */
        $capability = static::query()->create($attributes);

        app(MandateRegistrar::class)->forgetCachedPermissions();

        return $capability;
    }

    /**
     * {@inheritdoc}
     */
    public static function findByName(string $name, ?string $guard = null): CapabilityContract
    {
        $guard ??= Guard::getDefaultName();

        // Use registrar cache for efficient lookups
        $capability = app(MandateRegistrar::class)->getCapabilityByName($name, $guard);

        if (! $capability) {
            throw CapabilityNotFoundException::withName($name, $guard);
        }

        return $capability;
    }

    /**
     * {@inheritdoc}
     */
    public static function findById(int|string $id, ?string $guard = null): CapabilityContract
    {
        $query = static::query()->where('id', $id);

        if ($guard !== null) {
            $query->where('guard', $guard);
        }

        /** @var static|null $capability */
        $capability = $query->first();

        if (! $capability) {
            throw CapabilityNotFoundException::withId($id, $guard);
        }

        return $capability;
    }

    /**
     * {@inheritdoc}
     */
    public static function findOrCreate(string $name, ?string $guard = null): CapabilityContract
    {
        $guard ??= Guard::getDefaultName();

        /** @var static|null $capability */
        $capability = static::query()
            ->where('name', $name)
            ->where('guard', $guard)
            ->first();

        if ($capability) {
            return $capability;
        }

        /** @var static $capability */
        $capability = static::query()->create([
            'name' => $name,
            'guard' => $guard,
        ]);

        app(MandateRegistrar::class)->forgetCachedPermissions();

        return $capability;
    }

    /**
     * Get the value indicating whether the IDs are incrementing.
     */
    public function getIncrementing(): bool
    {
        $idType = config('mandate.model_id_type', 'int');

        return ! in_array($idType, ['uuid', 'ulid'], true);
    }

    /**
     * Get the primary key type.
     */
    public function getKeyType(): string
    {
        $idType = config('mandate.model_id_type', 'int');

        return in_array($idType, ['uuid', 'ulid'], true) ? 'string' : 'int';
    }

    /**
     * {@inheritdoc}
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            config('mandate.models.permission', Permission::class),
            config('mandate.tables.capability_permission', 'capability_permission'),
            config('mandate.column_names.capability_id', 'capability_id'),
            config('mandate.column_names.permission_id', 'permission_id')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            config('mandate.models.role', Role::class),
            config('mandate.tables.capability_role', 'capability_role'),
            config('mandate.column_names.capability_id', 'capability_id'),
            config('mandate.column_names.role_id', 'role_id')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function subjects(): MorphToMany
    {
        // Get the model class for this capability's guard from auth config
        $modelClass = Guard::getModelClassForGuard($this->guard) ?? Model::class;

        return $this->morphedByMany(
            $modelClass,
            'subject',
            config('mandate.tables.capability_subject', 'capability_subject'),
            config('mandate.column_names.capability_id', 'capability_id'),
            config('mandate.column_names.subject_morph_key', 'subject_id')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function grantPermission(string|array|PermissionContract $permissions): CapabilityContract
    {
        $permissions = $this->normalizePermissions($permissions);

        $this->permissions()->syncWithoutDetaching($permissions);

        app(MandateRegistrar::class)->forgetCachedPermissions();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function revokePermission(string|array|PermissionContract $permissions): CapabilityContract
    {
        $permissions = $this->normalizePermissions($permissions);

        $this->permissions()->detach($permissions);

        app(MandateRegistrar::class)->forgetCachedPermissions();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function syncPermissions(array $permissions): CapabilityContract
    {
        $normalized = [];

        foreach ($permissions as $permission) {
            if ($permission instanceof PermissionContract) {
                $normalized[] = $permission->getKey();
            } else {
                $permissionModel = $this->getPermissionModel()::findByName($permission, $this->guard);
                $normalized[] = $permissionModel->getKey();
            }
        }

        $this->permissions()->sync($normalized);

        app(MandateRegistrar::class)->forgetCachedPermissions();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function hasPermission(string|PermissionContract $permission): bool
    {
        // If permissions are already loaded, check in-memory (avoids N+1)
        if ($this->relationLoaded('permissions')) {
            $guard = $this->guard;
            if (is_string($permission)) {
                return $this->permissions->contains(
                    static fn (Permission $p) => $p->name === $permission && $p->guard === $guard // @phpstan-ignore argument.type
                );
            }

            return $this->permissions->contains(
                static fn (Permission $p) => $p->getKey() === $permission->getKey() // @phpstan-ignore argument.type
            );
        }

        if (is_string($permission)) {
            return $this->permissions()
                ->where('name', $permission)
                ->where('guard', $this->guard)
                ->exists();
        }

        return $this->permissions()
            ->where('id', $permission->getKey())
            ->exists();
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
        static::creating(function (self $model) {
            $idType = config('mandate.model_id_type', 'int');

            if ($idType === 'uuid' && empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            } elseif ($idType === 'ulid' && empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::ulid();
            }
        });

        static::saved(fn () => app(MandateRegistrar::class)->forgetCachedPermissions());
        static::deleted(fn () => app(MandateRegistrar::class)->forgetCachedPermissions());
    }

    /**
     * Normalize permissions to an array of IDs.
     *
     * @param  string|array<string|PermissionContract>|PermissionContract  $permissions
     * @return array<int|string>
     */
    protected function normalizePermissions(string|array|PermissionContract $permissions): array
    {
        if (! is_array($permissions)) {
            $permissions = [$permissions];
        }

        $normalized = [];

        foreach ($permissions as $permission) {
            if ($permission instanceof PermissionContract) {
                /** @var Permission $permission */
                Guard::assertMatch($this->guard, $permission->guard, 'permission');
                $normalized[] = $permission->getKey();
            } else {
                $permissionModel = $this->getPermissionModel()::findByName($permission, $this->guard);
                $normalized[] = $permissionModel->getKey();
            }
        }

        return $normalized;
    }

    /**
     * Get the permission model class.
     *
     * @return class-string<Permission>
     */
    protected function getPermissionModel(): string
    {
        return config('mandate.models.permission', Permission::class);
    }
}
