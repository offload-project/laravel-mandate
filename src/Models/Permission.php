<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use OffloadProject\Mandate\Contracts\PermissionContract;
use OffloadProject\Mandate\Contracts\RoleContract;
use OffloadProject\Mandate\Models\Concerns\HasContextScope;

final class Permission extends Model implements PermissionContract
{
    use HasContextScope;

    /** @var array<int, string> */
    protected $guarded = ['id'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('mandate.tables.permissions', 'mandate_permissions'));
    }

    /**
     * Find a permission by name and guard.
     */
    public static function findByName(string $name, ?string $guardName = null): ?PermissionContract
    {
        $guardName ??= config('auth.defaults.guard');

        /** @var PermissionContract|null */
        return self::query()
            ->where('name', $name)
            ->where('guard_name', $guardName)
            ->first();
    }

    /**
     * Find a permission by ID.
     */
    public static function findById(int|string $id): ?PermissionContract
    {
        /** @var PermissionContract|null */
        return self::query()->find($id);
    }

    /**
     * Create a new permission.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function createPermission(array $attributes): PermissionContract
    {
        $attributes['guard_name'] ??= config('auth.defaults.guard');

        /** @var PermissionContract */
        return self::query()->create($attributes);
    }

    /**
     * Get the roles relationship.
     */
    public function roles(): BelongsToMany
    {
        /** @var class-string<RoleContract&Model> $roleClass */
        $roleClass = config('mandate.models.role', Role::class);

        return $this->belongsToMany(
            $roleClass,
            config('mandate.tables.role_permissions', 'mandate_role_permissions'),
            config('mandate.columns.pivot_permission_key', 'permission_id'),
            config('mandate.columns.pivot_role_key', 'role_id')
        );
    }

    /**
     * Get the subjects (users, etc.) that have this permission directly.
     */
    public function subjects(string $subjectType): MorphToMany
    {
        $subjectMorphKey = config('mandate.columns.pivot_subject_morph_key', 'subject');

        return $this->morphedByMany(
            $subjectType,
            $subjectMorphKey,
            config('mandate.tables.subject_permissions', 'mandate_subject_permissions'),
            config('mandate.columns.pivot_permission_key', 'permission_id'),
            "{$subjectMorphKey}_id"
        );
    }
}
