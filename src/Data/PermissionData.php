<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Data;

use OffloadProject\Mandate\Attributes\PermissionsSet;
use OffloadProject\Mandate\Concerns\ExtractsAttributeMetadata;
use Spatie\LaravelData\Data;

/**
 * Data transfer object for permissions.
 */
final class PermissionData extends Data
{
    use ExtractsAttributeMetadata;

    public function __construct(
        public string $name,
        public string $label,
        public ?string $description = null,
        public ?string $set = null,
        public ?string $guard = null,
        public ?string $feature = null,
        public ?bool $active = null,
        public ?bool $featureActive = null,
        /**
         * Additional metadata for extensibility.
         *
         * This property allows package consumers to attach custom data to permissions
         * that may be useful for their specific application needs (e.g., icons, colors,
         * custom attributes for UI rendering, etc.).
         *
         * @var array<string, mixed>
         */
        public array $metadata = [],
    ) {}

    /**
     * Create from a class constant.
     *
     * @param  class-string  $class
     */
    public static function fromClassConstant(string $class, string $constantName, ?string $feature = null): self
    {
        $meta = self::extractConstantMetadata($class, $constantName, PermissionsSet::class);

        return new self(
            name: $meta['value'],
            label: $meta['label'],
            description: $meta['description'],
            set: $meta['set'],
            guard: $meta['guard'],
            feature: $feature,
        );
    }

    /**
     * Create a simple permission from name.
     */
    public static function simple(string $name, ?string $label = null, ?string $set = null): self
    {
        return new self(
            name: $name,
            label: $label ?? self::generateLabel($name),
            set: $set,
        );
    }

    /**
     * Check if this permission is available (feature is active or no feature required).
     */
    public function isAvailable(): bool
    {
        if ($this->feature === null) {
            return true;
        }

        return $this->featureActive ?? true;
    }

    /**
     * Check if the permission is effectively granted.
     * Both the permission must be assigned AND the feature must be active.
     */
    public function isGranted(): bool
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
            feature: $this->feature,
            active: $active,
            featureActive: $featureActive,
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
            feature: $this->feature,
            active: $this->active,
            featureActive: $this->featureActive,
            metadata: array_merge($this->metadata, $metadata),
        );
    }

    /**
     * Generate a label from a permission name.
     *
     * Handles both dot notation (users.view -> View Users) and
     * SCREAMING_SNAKE_CASE/snake_case/kebab-case.
     */
    protected static function generateLabel(string $name): string
    {
        // Handle dot notation: users.view -> View Users
        if (str_contains($name, '.')) {
            $parts = explode('.', $name);
            $action = ucfirst(end($parts));
            $resource = ucfirst($parts[0]);

            return "{$action} {$resource}";
        }

        // Fall back to parent implementation for SCREAMING_SNAKE_CASE, etc.
        return ucwords(str_replace(['_', '-'], ' ', mb_strtolower($name)));
    }
}
