<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use OffloadProject\Mandate\Contracts\Permission as PermissionContract;
use OffloadProject\Mandate\Exceptions\PermissionAlreadyExistsException;
use OffloadProject\Mandate\Exceptions\PermissionNotFoundException;
use OffloadProject\Mandate\Guard;
use OffloadProject\Mandate\MandateRegistrar;

/**
 * @property int|string $id
 * @property string $name
 * @property string $guard
 * @property string|null $context_type
 * @property int|string|null $context_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Permission extends Model implements PermissionContract
{
    /** @var list<string> */
    protected $fillable = [
        'name',
        'guard',
        'context_type',
        'context_id',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('mandate.tables.permissions', 'permissions'));

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
        $contextType = $attributes['context_type'] ?? null;
        $contextId = $attributes['context_id'] ?? null;

        // Check if permission already exists (including context if enabled)
        $query = static::query()
            ->where('name', $attributes['name'])
            ->where('guard', $guard);

        if (config('mandate.context.enabled', false)) {
            $query->where(config('mandate.column_names.context_morph_type', 'context_type'), $contextType)
                ->where(config('mandate.column_names.context_morph_key', 'context_id'), $contextId);
        }

        if ($query->exists()) {
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
     * Get the context model that this permission belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo<Model, $this>
     */
    public function context(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo(
            'context',
            config('mandate.column_names.context_morph_type', 'context_type'),
            config('mandate.column_names.context_morph_key', 'context_id')
        );
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
     * Get the capabilities that include this permission.
     */
    public function capabilities(): BelongsToMany
    {
        return $this->belongsToMany(
            config('mandate.models.capability', Capability::class),
            config('mandate.tables.capability_permission', 'capability_permission'),
            config('mandate.column_names.permission_id', 'permission_id'),
            config('mandate.column_names.capability_id', 'capability_id')
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
     * Scope query to specific context.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForContext(Builder $query, ?Model $context): Builder
    {
        if ($context === null) {
            return $query->whereNull(config('mandate.column_names.context_morph_type', 'context_type'))
                ->whereNull(config('mandate.column_names.context_morph_key', 'context_id'));
        }

        return $query->where(config('mandate.column_names.context_morph_type', 'context_type'), $context->getMorphClass())
            ->where(config('mandate.column_names.context_morph_key', 'context_id'), $context->getKey());
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
}
