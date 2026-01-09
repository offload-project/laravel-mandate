<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\CodeFirst;

/**
 * Data transfer object for a capability definition discovered from code.
 */
final readonly class CapabilityDefinition
{
    /**
     * @param  string  $name  The capability name (e.g., 'user-management')
     * @param  string  $guard  The authentication guard
     * @param  string|null  $label  Human-readable label
     * @param  string|null  $description  Longer description
     * @param  string  $sourceClass  The PHP class where this was defined
     * @param  string  $sourceConstant  The constant name where this was defined
     */
    public function __construct(
        public string $name,
        public string $guard,
        public ?string $label = null,
        public ?string $description = null,
        public string $sourceClass = '',
        public string $sourceConstant = '',
    ) {}

    /**
     * Create a definition from discovered attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function fromAttributes(array $attributes): self
    {
        return new self(
            name: $attributes['name'],
            guard: $attributes['guard'] ?? 'web',
            label: $attributes['label'] ?? null,
            description: $attributes['description'] ?? null,
            sourceClass: $attributes['source_class'] ?? '',
            sourceConstant: $attributes['source_constant'] ?? '',
        );
    }

    /**
     * Get a unique identifier for this definition.
     */
    public function getIdentifier(): string
    {
        return "{$this->guard}:{$this->name}";
    }
}
