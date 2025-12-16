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
     * Generate a name from class name.
     */
    private static function generateName(string $class): string
    {
        $className = class_basename($class);

        return mb_strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', str_replace('Feature', '', $className)));
    }

    /**
     * Generate a label from class name.
     */
    private static function generateLabel(string $class): string
    {
        $className = class_basename($class);

        return ucwords(preg_replace('/([a-z])([A-Z])/', '$1 $2', str_replace('Feature', '', $className)));
    }
}
