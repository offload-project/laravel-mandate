<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Data;

use OffloadProject\Mandate\Attributes\RoleSet;
use OffloadProject\Mandate\Concerns\ExtractsAttributeMetadata;
use Spatie\LaravelData\Data;

/**
 * Data transfer object for roles.
 */
final class RoleData extends Data
{
    use ExtractsAttributeMetadata;

    public function __construct(
        public string $name,
        public string $label,
        public ?string $description = null,
        public ?string $set = null,
        public ?string $guard = null,
        public ?string $scope = null,
        public ?string $feature = null,
        public ?bool $active = null,
        public ?bool $featureActive = null,
        /** @var array<string> Direct permissions explicitly assigned to this role */
        public array $permissions = [],
        /** @var array<string> Permissions inherited from parent roles */
        public array $inheritedPermissions = [],
        /** @var array<string> Parent role names this role inherits from */
        public array $inheritsFrom = [],
        /**
         * Additional metadata for extensibility.
         *
         * This property allows package consumers to attach custom data to roles
         * that may be useful for their specific application needs (e.g., icons, colors,
         * hierarchy level, custom attributes for UI rendering, etc.).
         *
         * @var array<string, mixed>
         */
        public array $metadata = [],
    ) {}

    /**
     * Create from a class constant.
     *
     * @param  class-string  $class
     * @param  array<string>  $permissions
     */
    public static function fromClassConstant(string $class, string $constantName, ?string $feature = null, array $permissions = []): self
    {
        $meta = self::extractConstantMetadata($class, $constantName, RoleSet::class);

        return new self(
            name: $meta['value'],
            label: $meta['label'],
            description: $meta['description'],
            set: $meta['set'],
            guard: $meta['guard'],
            scope: $meta['scope'],
            feature: $feature,
            permissions: $permissions,
            inheritsFrom: $meta['inheritsFrom'],
        );
    }

    /**
     * Create from a cached array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            label: $data['label'],
            description: $data['description'] ?? null,
            set: $data['set'] ?? null,
            guard: $data['guard'] ?? null,
            scope: $data['scope'] ?? null,
            feature: $data['feature'] ?? null,
            active: $data['active'] ?? null,
            featureActive: $data['featureActive'] ?? null,
            permissions: $data['permissions'] ?? [],
            inheritedPermissions: $data['inheritedPermissions'] ?? [],
            inheritsFrom: $data['inheritsFrom'] ?? [],
            metadata: $data['metadata'] ?? [],
        );
    }

    /**
     * Get all effective permissions (direct + inherited, deduplicated).
     *
     * @return array<string>
     */
    public function allPermissions(): array
    {
        return array_values(array_unique(
            array_merge($this->permissions, $this->inheritedPermissions)
        ));
    }

    /**
     * Check if this role has been granted a specific permission (direct or inherited).
     */
    public function granted(string $permission): bool
    {
        return in_array($permission, $this->permissions, true)
            || in_array($permission, $this->inheritedPermissions, true);
    }

    /**
     * Check if a permission is inherited (not directly assigned).
     */
    public function isInheritedPermission(string $permission): bool
    {
        return in_array($permission, $this->inheritedPermissions, true)
            && ! in_array($permission, $this->permissions, true);
    }

    /**
     * Check if this role is available (feature is active or no feature required).
     */
    public function isAvailable(): bool
    {
        if ($this->feature === null) {
            return true;
        }

        return $this->featureActive ?? true;
    }

    /**
     * Check if the role is effectively assigned.
     */
    public function isAssigned(): bool
    {
        return $this->active === true && $this->isAvailable();
    }

    /**
     * Create a copy with updated active status.
     */
    public function withStatus(?bool $active, ?bool $featureActive): self
    {
        return new self(
            name: $this->name,
            label: $this->label,
            description: $this->description,
            set: $this->set,
            guard: $this->guard,
            scope: $this->scope,
            feature: $this->feature,
            active: $active,
            featureActive: $featureActive,
            permissions: $this->permissions,
            inheritedPermissions: $this->inheritedPermissions,
            inheritsFrom: $this->inheritsFrom,
            metadata: $this->metadata,
        );
    }

    /**
     * Create a copy with additional metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): self
    {
        return new self(
            name: $this->name,
            label: $this->label,
            description: $this->description,
            set: $this->set,
            guard: $this->guard,
            scope: $this->scope,
            feature: $this->feature,
            active: $this->active,
            featureActive: $this->featureActive,
            permissions: $this->permissions,
            inheritedPermissions: $this->inheritedPermissions,
            inheritsFrom: $this->inheritsFrom,
            metadata: array_merge($this->metadata, $metadata),
        );
    }

    /**
     * Create a copy with resolved inheritance.
     *
     * @param  array<string>  $inheritedPermissions
     * @param  array<string>  $inheritsFrom
     */
    public function withInheritance(array $inheritedPermissions, array $inheritsFrom): self
    {
        return new self(
            name: $this->name,
            label: $this->label,
            description: $this->description,
            set: $this->set,
            guard: $this->guard,
            scope: $this->scope,
            feature: $this->feature,
            active: $this->active,
            featureActive: $this->featureActive,
            permissions: $this->permissions,
            inheritedPermissions: $inheritedPermissions,
            inheritsFrom: $inheritsFrom,
            metadata: $this->metadata,
        );
    }
}
