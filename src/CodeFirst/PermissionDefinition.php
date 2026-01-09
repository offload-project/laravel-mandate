<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\CodeFirst;

/**
 * Data transfer object for a permission definition discovered from code.
 */
final readonly class PermissionDefinition
{
    /**
     * @param  string  $name  The permission name (e.g., 'user:view')
     * @param  string  $guard  The authentication guard
     * @param  string|null  $label  Human-readable label
     * @param  string|null  $description  Longer description
     * @param  string|null  $contextClass  Context model class for scoped permissions
     * @param  array<string>  $capabilities  Capability names this permission belongs to
     * @param  string  $sourceClass  The PHP class where this was defined
     * @param  string  $sourceConstant  The constant name where this was defined
     */
    public function __construct(
        public string $name,
        public string $guard,
        public ?string $label = null,
        public ?string $description = null,
        public ?string $contextClass = null,
        public array $capabilities = [],
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
            contextClass: $attributes['context'] ?? null,
            capabilities: $attributes['capabilities'] ?? [],
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
