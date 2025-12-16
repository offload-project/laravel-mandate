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
        public ?string $feature = null,
        public ?bool $active = null,
        public ?bool $featureActive = null,
        /** @var array<string> */
        public array $permissions = [],
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
            feature: $feature,
            permissions: $permissions,
        );
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
            feature: $this->feature,
            active: $active,
            featureActive: $featureActive,
            permissions: $this->permissions,
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
            permissions: $this->permissions,
            metadata: array_merge($this->metadata, $metadata),
        );
    }
}
