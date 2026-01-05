<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Data;

use Spatie\LaravelData\Data;

/**
 * Data transfer object for features with their associated permissions and roles.
 */
final class FeatureData extends Data
{
    public function __construct(
        /** @var class-string */
        public string $class,
        public string $name,
        public string $label,
        public ?string $description = null,
        public ?bool $active = null,
        /** @var array<PermissionData> */
        public array $permissions = [],
        /** @var array<RoleData> */
        public array $roles = [],
        /** @var array<string, mixed> */
        public array $metadata = [],
    ) {}

    /**
     * Create from a feature class instance.
     *
     * @param  array<PermissionData>  $permissions
     * @param  array<RoleData>  $roles
     */
    public static function fromFeature(
        object $feature,
        array $permissions = [],
        array $roles = [],
        ?bool $active = null,
    ): self {
        return new self(
            class: $feature::class,
            name: $feature->name ?? self::generateName($feature::class),
            label: $feature->label ?? self::generateLabel($feature::class),
            description: $feature->description ?? null,
            active: $active,
            permissions: $permissions,
            roles: $roles,
            metadata: method_exists($feature, 'metadata') ? $feature->metadata() : [],
        );
    }

    /**
     * Create from a cached array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $permissions = array_map(
            fn (array $p) => PermissionData::fromArray($p),
            $data['permissions'] ?? []
        );

        $roles = array_map(
            fn (array $r) => RoleData::fromArray($r),
            $data['roles'] ?? []
        );

        return new self(
            class: $data['class'],
            name: $data['name'],
            label: $data['label'],
            description: $data['description'] ?? null,
            active: $data['active'] ?? null,
            permissions: $permissions,
            roles: $roles,
            metadata: $data['metadata'] ?? [],
        );
    }

    /**
     * Create a copy with updated active status.
     */
    public function withActive(?bool $active): self
    {
        return new self(
            class: $this->class,
            name: $this->name,
            label: $this->label,
            description: $this->description,
            active: $active,
            permissions: $this->permissions,
            roles: $this->roles,
            metadata: $this->metadata,
        );
    }

    /**
     * Generate a name from class name.
     */
    private static function generateName(string $class): string
    {
        $className = class_basename($class);
        $withoutFeature = str_replace('Feature', '', $className);
        $result = preg_replace('/([a-z])([A-Z])/', '$1-$2', $withoutFeature);

        return mb_strtolower($result ?? $withoutFeature);
    }

    /**
     * Generate a label from class name.
     */
    private static function generateLabel(string $class): string
    {
        $className = class_basename($class);
        $withoutFeature = str_replace('Feature', '', $className);
        $result = preg_replace('/([a-z])([A-Z])/', '$1 $2', $withoutFeature);

        return ucwords($result ?? $withoutFeature);
    }
}
