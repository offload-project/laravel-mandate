<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use OffloadProject\Mandate\Contracts\Capability as CapabilityContract;
use OffloadProject\Mandate\Contracts\Permission as PermissionContract;
use OffloadProject\Mandate\Contracts\Role as RoleContract;
use OffloadProject\Mandate\Events\CapabilityAssigned;
use OffloadProject\Mandate\Events\CapabilityRemoved;
use OffloadProject\Mandate\Exceptions\RoleAlreadyExistsException;
use OffloadProject\Mandate\Exceptions\RoleNotFoundException;
use OffloadProject\Mandate\Guard;
use OffloadProject\Mandate\MandateRegistrar;

/**
 * @property int|string $id
 * @property string $name
 * @property string $guard
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Role extends Model implements RoleContract
{
    /** @var list<string> */
    protected $fillable = [
        'name',
        'guard',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('mandate.tables.roles', 'roles'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function create(array $attributes): static
    {
        $guard = $attributes['guard'] ?? Guard::getDefaultName();

        // Check if role already exists
        $existing = static::query()
            ->where('name', $attributes['name'])
            ->where('guard', $guard)
            ->first();

        if ($existing) {
            throw RoleAlreadyExistsException::create($attributes['name'], $guard);
        }

        $attributes['guard'] = $guard;

        /** @var static $role */
        $role = static::query()->create($attributes);

        app(MandateRegistrar::class)->forgetCachedPermissions();

        return $role;
    }

    /**
     * {@inheritdoc}
     */
    public static function findByName(string $name, ?string $guard = null): RoleContract
    {
        $guard ??= Guard::getDefaultName();

        // Use registrar cache for efficient lookups
        $role = app(MandateRegistrar::class)->getRoleByName($name, $guard);

        if (! $role) {
            throw RoleNotFoundException::withName($name, $guard);
        }

        return $role;
    }

    /**
     * {@inheritdoc}
     */
    public static function findById(int|string $id, ?string $guard = null): RoleContract
    {
        $query = static::query()->where('id', $id);

        if ($guard !== null) {
            $query->where('guard', $guard);
        }

        /** @var static|null $role */
        $role = $query->first();

        if (! $role) {
            throw RoleNotFoundException::withId($id, $guard);
        }

        return $role;
    }

    /**
     * {@inheritdoc}
     */
    public static function findOrCreate(string $name, ?string $guard = null): RoleContract
    {
        $guard ??= Guard::getDefaultName();

        /** @var static|null $role */
        $role = static::query()
            ->where('name', $name)
            ->where('guard', $guard)
            ->first();

        if ($role) {
            return $role;
        }

        /** @var static $role */
        $role = static::query()->create([
            'name' => $name,
            'guard' => $guard,
        ]);

        app(MandateRegistrar::class)->forgetCachedPermissions();

        return $role;
    }

    /**
     * {@inheritdoc}
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            config('mandate.models.permission', Permission::class),
            config('mandate.tables.permission_role', 'permission_role'),
            config('mandate.column_names.role_id', 'role_id'),
            config('mandate.column_names.permission_id', 'permission_id')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function subjects(): MorphToMany
    {
        // Get the model class for this role's guard from auth config
        $modelClass = Guard::getModelClassForGuard($this->guard) ?? Model::class;

        return $this->morphedByMany(
            $modelClass,
            'subject',
            config('mandate.tables.role_subject', 'role_subject'),
            config('mandate.column_names.role_id', 'role_id'),
            config('mandate.column_names.subject_morph_key', 'subject_id')
        );
    }

    /**
     * Get the capabilities assigned to this role.
     */
    public function capabilities(): BelongsToMany
    {
        return $this->belongsToMany(
            config('mandate.models.capability', Capability::class),
            config('mandate.tables.capability_role', 'capability_role'),
            config('mandate.column_names.role_id', 'role_id'),
            config('mandate.column_names.capability_id', 'capability_id')
        );
    }

    /**
     * Assign capability(s) to this role.
     *
     * @param  string|array<string>|CapabilityContract  $capabilities
     */
    public function assignCapability(string|array|CapabilityContract $capabilities): RoleContract
    {
        if (! config('mandate.capabilities.enabled', false)) {
            return $this;
        }

        $capabilityNames = $this->collectCapabilityNames($capabilities);
        $normalizedIds = $this->normalizeCapabilities($capabilities);

        $this->capabilities()->syncWithoutDetaching($normalizedIds);

        app(MandateRegistrar::class)->forgetCachedPermissions();

        if (config('mandate.events', false)) {
            CapabilityAssigned::dispatch($this, $capabilityNames);
        }

        return $this;
    }

    /**
     * Remove capability(s) from this role.
     *
     * @param  string|array<string>|CapabilityContract  $capabilities
     */
    public function removeCapability(string|array|CapabilityContract $capabilities): RoleContract
    {
        if (! config('mandate.capabilities.enabled', false)) {
            return $this;
        }

        $capabilityNames = $this->collectCapabilityNames($capabilities);
        $normalizedIds = $this->normalizeCapabilities($capabilities);

        $this->capabilities()->detach($normalizedIds);

        app(MandateRegistrar::class)->forgetCachedPermissions();

        if (config('mandate.events', false)) {
            CapabilityRemoved::dispatch($this, $capabilityNames);
        }

        return $this;
    }

    /**
     * Sync capabilities on this role (replace all existing).
     *
     * @param  array<string|CapabilityContract>  $capabilities
     */
    public function syncCapabilities(array $capabilities): RoleContract
    {
        if (! config('mandate.capabilities.enabled', false)) {
            $this->capabilities()->sync([]);

            return $this;
        }

        $normalized = [];

        foreach ($capabilities as $capability) {
            if ($capability instanceof CapabilityContract) {
                $normalized[] = $capability->getKey();
            } else {
                $capabilityModel = $this->getCapabilityModel()::findByName($capability, $this->guard);
                $normalized[] = $capabilityModel->getKey();
            }
        }

        $this->capabilities()->sync($normalized);

        app(MandateRegistrar::class)->forgetCachedPermissions();

        return $this;
    }

    /**
     * Check if the role has a specific capability.
     */
    public function hasCapability(string|CapabilityContract $capability): bool
    {
        if (! config('mandate.capabilities.enabled', false)) {
            return false;
        }

        if ($this->relationLoaded('capabilities')) {
            $guard = $this->guard;
            if (is_string($capability)) {
                return $this->capabilities->contains(
                    static fn (Capability $c) => $c->name === $capability && $c->guard === $guard // @phpstan-ignore argument.type
                );
            }

            return $this->capabilities->contains(
                static fn (Capability $c) => $c->getKey() === $capability->getKey() // @phpstan-ignore argument.type
            );
        }

        if (is_string($capability)) {
            return $this->capabilities()
                ->where('name', $capability)
                ->where('guard', $this->guard)
                ->exists();
        }

        return $this->capabilities()
            ->where('id', $capability->getKey())
            ->exists();
    }

    /**
     * Get all permissions including those from capabilities.
     *
     * @return Collection<int, PermissionContract>
     */
    public function getAllPermissions(): Collection
    {
        // Get direct permissions
        $permissions = $this->permissions->keyBy('id');

        // Add permissions from capabilities if enabled
        if (config('mandate.capabilities.enabled', false)) {
            $capabilityPermissions = $this->getPermissionsViaCapabilities();
            $permissions = $permissions->merge($capabilityPermissions->keyBy('id')); // @phpstan-ignore argument.type
        }

        return $permissions->values();
    }

    /**
     * Get permissions granted via capabilities.
     *
     * @return Collection<int, PermissionContract>
     */
    public function getPermissionsViaCapabilities(): Collection
    {
        if (! config('mandate.capabilities.enabled', false)) {
            return collect();
        }

        $capabilities = $this->relationLoaded('capabilities') && $this->capabilities->every(fn ($c) => $c->relationLoaded('permissions'))
            ? $this->capabilities
            : $this->capabilities()->with('permissions')->get();

        /** @var \Illuminate\Database\Eloquent\Collection<int, Capability> $capabilities */
        return $capabilities->flatMap(fn (Capability $c) => $c->permissions)->unique('id')->values(); // @phpstan-ignore argument.type
    }

    /**
     * {@inheritdoc}
     */
    public function grantPermission(string|array|PermissionContract $permissions): RoleContract
    {
        $permissions = $this->normalizePermissions($permissions);

        $this->permissions()->syncWithoutDetaching($permissions);

        app(MandateRegistrar::class)->forgetCachedPermissions();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function revokePermission(string|array|PermissionContract $permissions): RoleContract
    {
        $permissions = $this->normalizePermissions($permissions);

        $this->permissions()->detach($permissions);

        app(MandateRegistrar::class)->forgetCachedPermissions();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function syncPermissions(array $permissions): RoleContract
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
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForGuard(\Illuminate\Database\Eloquent\Builder $query, string $guard): \Illuminate\Database\Eloquent\Builder
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

    /**
     * Normalize capabilities to an array of IDs.
     *
     * @param  string|array<string|CapabilityContract>|CapabilityContract  $capabilities
     * @return array<int|string>
     */
    protected function normalizeCapabilities(string|array|CapabilityContract $capabilities): array
    {
        if (! is_array($capabilities)) {
            $capabilities = [$capabilities];
        }

        $normalized = [];

        foreach ($capabilities as $capability) {
            if ($capability instanceof CapabilityContract) {
                /** @var Capability $capability */
                Guard::assertMatch($this->guard, $capability->guard, 'capability');
                $normalized[] = $capability->getKey();
            } else {
                $capabilityModel = $this->getCapabilityModel()::findByName($capability, $this->guard);
                $normalized[] = $capabilityModel->getKey();
            }
        }

        return $normalized;
    }

    /**
     * Collect capability names from various input types.
     *
     * @param  string|array<string|CapabilityContract>|CapabilityContract  $capabilities
     * @return array<string>
     */
    protected function collectCapabilityNames(string|array|CapabilityContract $capabilities): array
    {
        if (! is_array($capabilities)) {
            $capabilities = [$capabilities];
        }

        return array_map(function ($capability) {
            if ($capability instanceof CapabilityContract) {
                return $capability->name; // @phpstan-ignore property.notFound
            }

            return $capability;
        }, $capabilities);
    }

    /**
     * Get the capability model class.
     *
     * @return class-string<Capability>
     */
    protected function getCapabilityModel(): string
    {
        return config('mandate.models.capability', Capability::class);
    }
}
