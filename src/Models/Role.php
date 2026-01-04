<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use OffloadProject\Mandate\Contracts\PermissionContract;
use OffloadProject\Mandate\Contracts\RoleContract;
use OffloadProject\Mandate\Models\Concerns\HasContextScope;
use OffloadProject\Mandate\Models\Concerns\HasHierarchy;

final class Role extends Model implements RoleContract
{
    use HasContextScope;
    use HasHierarchy;

    /** @var array<int, string> */
    protected $guarded = ['id'];

    /** @var array<string, string> */
    protected $casts = [
        'inherits_from' => 'array',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('mandate.tables.roles', 'mandate_roles'));
    }

    /**
     * Find a role by name and guard.
     */
    public static function findByName(string $name, ?string $guardName = null): ?RoleContract
    {
        $guardName ??= config('auth.defaults.guard');

        /** @var RoleContract|null */
        return self::query()
            ->where('name', $name)
            ->where('guard_name', $guardName)
            ->first();
    }

    /**
     * Find a role by ID.
     */
    public static function findById(int|string $id): ?RoleContract
    {
        /** @var RoleContract|null */
        return self::query()->find($id);
    }

    /**
     * Create a new role.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function createRole(array $attributes): RoleContract
    {
        $attributes['guard_name'] ??= config('auth.defaults.guard');

        /** @var RoleContract */
        return self::query()->create($attributes);
    }

    /**
     * Get the permissions relationship.
     */
    public function permissions(): BelongsToMany
    {
        /** @var class-string<PermissionContract&Model> $permissionClass */
        $permissionClass = config('mandate.models.permission', Permission::class);

        return $this->belongsToMany(
            $permissionClass,
            config('mandate.tables.role_permissions', 'mandate_role_permissions'),
            config('mandate.columns.pivot_role_key', 'role_id'),
            config('mandate.columns.pivot_permission_key', 'permission_id')
        );
    }

    /**
     * Get the subjects (users, etc.) that have this role.
     */
    public function subjects(string $subjectType): MorphToMany
    {
        $subjectMorphKey = config('mandate.columns.pivot_subject_morph_key', 'subject');

        return $this->morphedByMany(
            $subjectType,
            $subjectMorphKey,
            config('mandate.tables.subject_roles', 'mandate_subject_roles'),
            config('mandate.columns.pivot_role_key', 'role_id'),
            "{$subjectMorphKey}_id"
        );
    }

    /**
     * Grant permissions to this role.
     *
     * @param  string|iterable<string>|PermissionContract  $permissions
     * @return $this
     */
    public function grantPermissions(string|iterable|PermissionContract $permissions): static
    {
        $permissions = $this->resolvePermissions($permissions);

        $this->permissions()->syncWithoutDetaching($permissions->pluck('id'));

        return $this;
    }

    /**
     * Revoke permissions from this role.
     *
     * @param  string|iterable<string>|PermissionContract  $permissions
     * @return $this
     */
    public function revokePermissions(string|iterable|PermissionContract $permissions): static
    {
        $permissions = $this->resolvePermissions($permissions);

        $this->permissions()->detach($permissions->pluck('id'));

        return $this;
    }

    /**
     * Sync permissions for this role.
     *
     * @param  iterable<string|PermissionContract>  $permissions
     * @return $this
     */
    public function syncPermissions(iterable $permissions): static
    {
        $resolved = $this->resolvePermissions($permissions);

        $this->permissions()->sync($resolved->pluck('id'));

        return $this;
    }

    /**
     * Get all permissions including inherited ones.
     *
     * @return Collection<int, PermissionContract&Model>
     */
    public function allPermissions(): Collection
    {
        $permissions = $this->permissions;

        foreach ($this->getAllAncestors() as $ancestor) {
            $permissions = $permissions->merge($ancestor->permissions);
        }

        return $permissions->unique('id')->values();
    }

    /**
     * Check if this role has been granted a specific permission.
     */
    public function granted(string|PermissionContract $permission): bool
    {
        $permissionName = $permission instanceof PermissionContract
            ? $permission->getAttribute('name')
            : $permission;

        return $this->allPermissions()->contains(fn ($p) => $p->getAttribute('name') === $permissionName);
    }

    /**
     * Resolve permissions to a collection of Permission models.
     *
     * @param  string|iterable<string|PermissionContract>|PermissionContract  $permissions
     * @return Collection<int, PermissionContract&Model>
     */
    protected function resolvePermissions(string|iterable|PermissionContract $permissions): Collection
    {
        if ($permissions instanceof PermissionContract) {
            return collect([$permissions]);
        }

        if (is_string($permissions)) {
            $permissions = [$permissions];
        }

        /** @var class-string<PermissionContract&Model> $permissionClass */
        $permissionClass = config('mandate.models.permission', Permission::class);

        $resolved = collect();

        foreach ($permissions as $permission) {
            if ($permission instanceof PermissionContract) {
                $resolved->push($permission);
            } else {
                $model = $permissionClass::findByName($permission, $this->getAttribute('guard_name'));
                if ($model !== null) {
                    $resolved->push($model);
                }
            }
        }

        return $resolved;
    }
}
