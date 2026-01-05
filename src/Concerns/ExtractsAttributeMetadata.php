<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Concerns;

use OffloadProject\Mandate\Attributes\Description;
use OffloadProject\Mandate\Attributes\Guard;
use OffloadProject\Mandate\Attributes\Inherits;
use OffloadProject\Mandate\Attributes\Label;
use OffloadProject\Mandate\Attributes\PermissionsSet;
use OffloadProject\Mandate\Attributes\RoleSet;
use OffloadProject\Mandate\Attributes\Scope;
use ReflectionClass;
use ReflectionClassConstant;

/**
 * Trait for extracting metadata from class attributes.
 */
trait ExtractsAttributeMetadata
{
    /**
     * Extract metadata from a class constant's attributes.
     *
     * @param  class-string  $class
     * @param  class-string  $setAttributeClass  The attribute class for the set (PermissionsSet or RoleSet)
     * @return array{value: string, label: string, description: ?string, set: ?string, guard: ?string, scope: ?string, inheritsFrom: array<string>}
     */
    protected static function extractConstantMetadata(
        string $class,
        string $constantName,
        string $setAttributeClass,
    ): array {
        $reflection = new ReflectionClass($class);
        $constantReflection = new ReflectionClassConstant($class, $constantName);

        // Get the constant value
        $value = (string) $constantReflection->getValue();

        // Get label from attribute or generate from constant name
        $labelAttr = $constantReflection->getAttributes(Label::class)[0] ?? null;
        $label = $labelAttr
            ? $labelAttr->newInstance()->value
            : static::generateLabel($constantName);

        // Get description from attribute
        $descAttr = $constantReflection->getAttributes(Description::class)[0] ?? null;
        $description = $descAttr?->newInstance()->value;

        // Get set from class-level attribute
        $setAttr = $reflection->getAttributes($setAttributeClass)[0] ?? null;
        /** @var PermissionsSet|RoleSet|null $setInstance */
        $setInstance = $setAttr?->newInstance();
        $set = $setInstance?->name;

        // Get guard from constant-level or class-level attribute
        $guardAttr = $constantReflection->getAttributes(Guard::class)[0]
            ?? $reflection->getAttributes(Guard::class)[0]
            ?? null;
        $guard = $guardAttr?->newInstance()->name;

        // Get scope from constant-level or class-level attribute
        $scopeAttr = $constantReflection->getAttributes(Scope::class)[0]
            ?? $reflection->getAttributes(Scope::class)[0]
            ?? null;
        $scope = $scopeAttr?->newInstance()->name;

        // Get parent roles from Inherits attribute (for role hierarchy)
        $inheritsAttr = $constantReflection->getAttributes(Inherits::class)[0] ?? null;
        $inheritsFrom = $inheritsAttr?->newInstance()->parents ?? [];

        return compact('value', 'label', 'description', 'set', 'guard', 'scope', 'inheritsFrom');
    }

    /**
     * Generate a label from a constant name.
     */
    protected static function generateLabel(string $name): string
    {
        return ucwords(str_replace(['_', '-'], ' ', mb_strtolower($name)));
    }
}
