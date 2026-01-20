<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\CodeFirst;

use Generator;
use Illuminate\Support\Collection;
use OffloadProject\Mandate\Attributes\Capability as CapabilityAttribute;
use OffloadProject\Mandate\Attributes\Context;
use OffloadProject\Mandate\Attributes\Description;
use OffloadProject\Mandate\Attributes\Guard;
use OffloadProject\Mandate\Attributes\Label;
use ReflectionClass;
use ReflectionClassConstant;
use Symfony\Component\Finder\Finder;

/**
 * Discovers permission, role, and capability definitions from PHP classes.
 */
final class DefinitionDiscoverer
{
    /**
     * Discover permission definitions from the given paths.
     *
     * @param  array<string>|string  $paths
     * @return Collection<int, PermissionDefinition>
     */
    public function discoverPermissions(array|string $paths): Collection
    {
        $paths = (array) $paths;
        $definitions = collect();

        foreach ($this->getClassesFromPaths($paths) as $class) {
            $definitions = $definitions->merge(
                $this->extractPermissionsFromClass($class)
            );
        }

        return $definitions;
    }

    /**
     * Discover role definitions from the given paths.
     *
     * @param  array<string>|string  $paths
     * @return Collection<int, RoleDefinition>
     */
    public function discoverRoles(array|string $paths): Collection
    {
        $paths = (array) $paths;
        $definitions = collect();

        foreach ($this->getClassesFromPaths($paths) as $class) {
            $definitions = $definitions->merge(
                $this->extractRolesFromClass($class)
            );
        }

        return $definitions;
    }

    /**
     * Discover capability definitions from the given paths.
     *
     * @param  array<string>|string  $paths
     * @return Collection<int, CapabilityDefinition>
     */
    public function discoverCapabilities(array|string $paths): Collection
    {
        $paths = (array) $paths;
        $definitions = collect();

        foreach ($this->getClassesFromPaths($paths) as $class) {
            $definitions = $definitions->merge(
                $this->extractCapabilitiesFromClass($class)
            );
        }

        return $definitions;
    }

    /**
     * Get all PHP classes from the given paths.
     *
     * @param  array<string>  $paths
     * @return Generator<ReflectionClass<object>>
     */
    private function getClassesFromPaths(array $paths): Generator
    {
        $validPaths = array_filter($paths, fn (string $path) => is_dir($path));

        if (empty($validPaths)) {
            return;
        }

        $finder = (new Finder)
            ->files()
            ->name('*.php')
            ->in($validPaths);

        foreach ($finder as $file) {
            $className = $this->getClassNameFromFile($file->getRealPath());

            if ($className === null) {
                continue;
            }

            if (! class_exists($className)) {
                continue;
            }

            yield new ReflectionClass($className);
        }
    }

    /**
     * Extract the fully qualified class name from a PHP file.
     */
    private function getClassNameFromFile(string $filePath): ?string
    {
        $contents = file_get_contents($filePath);

        if ($contents === false) {
            return null;
        }

        $namespace = null;
        $class = null;

        if (preg_match('/namespace\s+([^;]+);/', $contents, $matches)) {
            $namespace = $matches[1];
        }

        if (preg_match('/(?:final\s+)?(?:readonly\s+)?(?:abstract\s+)?class\s+(\w+)/', $contents, $matches)) {
            $class = $matches[1];
        }

        if ($class === null) {
            return null;
        }

        return $namespace !== null ? "{$namespace}\\{$class}" : $class;
    }

    /**
     * Extract permission definitions from a class.
     *
     * @param  ReflectionClass<object>  $class
     * @return array<PermissionDefinition>
     */
    private function extractPermissionsFromClass(ReflectionClass $class): array
    {
        $definitions = [];
        $classGuard = $this->getClassGuard($class);
        $classLabel = $this->getClassAttribute($class, Label::class)?->value;
        $classDescription = $this->getClassAttribute($class, Description::class)?->value;
        $classCapabilities = $this->getClassCapabilityNames($class);

        foreach ($class->getReflectionConstants(ReflectionClassConstant::IS_PUBLIC) as $constant) {
            $value = $constant->getValue();

            if (! is_string($value)) {
                continue;
            }

            $labelAttr = $this->getConstantAttribute($constant, Label::class);
            $descAttr = $this->getConstantAttribute($constant, Description::class);
            $contextAttr = $this->getConstantAttribute($constant, Context::class);
            $constantCapabilities = $this->getCapabilityNames($constant);

            $definitions[] = PermissionDefinition::fromAttributes([
                'name' => $value,
                'guard' => $classGuard,
                'label' => $labelAttr !== null ? $labelAttr->value : $classLabel,
                'description' => $descAttr !== null ? $descAttr->value : $classDescription,
                'context' => $contextAttr?->modelClass,
                'capabilities' => array_unique(array_merge($classCapabilities, $constantCapabilities)),
                'source_class' => $class->getName(),
                'source_constant' => $constant->getName(),
            ]);
        }

        return $definitions;
    }

    /**
     * Extract role definitions from a class.
     *
     * @param  ReflectionClass<object>  $class
     * @return array<RoleDefinition>
     */
    private function extractRolesFromClass(ReflectionClass $class): array
    {
        $definitions = [];
        $classGuard = $this->getClassGuard($class);
        $classLabel = $this->getClassAttribute($class, Label::class)?->value;
        $classDescription = $this->getClassAttribute($class, Description::class)?->value;

        foreach ($class->getReflectionConstants(ReflectionClassConstant::IS_PUBLIC) as $constant) {
            $value = $constant->getValue();

            if (! is_string($value)) {
                continue;
            }

            $labelAttr = $this->getConstantAttribute($constant, Label::class);
            $descAttr = $this->getConstantAttribute($constant, Description::class);
            $contextAttr = $this->getConstantAttribute($constant, Context::class);

            $definitions[] = RoleDefinition::fromAttributes([
                'name' => $value,
                'guard' => $classGuard,
                'label' => $labelAttr !== null ? $labelAttr->value : $classLabel,
                'description' => $descAttr !== null ? $descAttr->value : $classDescription,
                'context' => $contextAttr?->modelClass,
                'source_class' => $class->getName(),
                'source_constant' => $constant->getName(),
            ]);
        }

        return $definitions;
    }

    /**
     * Extract capability definitions from a class.
     *
     * @param  ReflectionClass<object>  $class
     * @return array<CapabilityDefinition>
     */
    private function extractCapabilitiesFromClass(ReflectionClass $class): array
    {
        $definitions = [];
        $classGuard = $this->getClassGuard($class);
        $classLabel = $this->getClassAttribute($class, Label::class)?->value;
        $classDescription = $this->getClassAttribute($class, Description::class)?->value;

        foreach ($class->getReflectionConstants(ReflectionClassConstant::IS_PUBLIC) as $constant) {
            $value = $constant->getValue();

            if (! is_string($value)) {
                continue;
            }

            $labelAttr = $this->getConstantAttribute($constant, Label::class);
            $descAttr = $this->getConstantAttribute($constant, Description::class);

            $definitions[] = CapabilityDefinition::fromAttributes([
                'name' => $value,
                'guard' => $classGuard,
                'label' => $labelAttr !== null ? $labelAttr->value : $classLabel,
                'description' => $descAttr !== null ? $descAttr->value : $classDescription,
                'source_class' => $class->getName(),
                'source_constant' => $constant->getName(),
            ]);
        }

        return $definitions;
    }

    /**
     * Get the guard attribute value from a class.
     *
     * @param  ReflectionClass<object>  $class
     */
    private function getClassGuard(ReflectionClass $class): string
    {
        $guard = $this->getClassAttribute($class, Guard::class);

        return $guard !== null ? $guard->name : \OffloadProject\Mandate\Guard::getDefaultName();
    }

    /**
     * Get an attribute instance from a class.
     *
     * @template T of object
     *
     * @param  ReflectionClass<object>  $class
     * @param  class-string<T>  $attributeClass
     * @return T|null
     */
    private function getClassAttribute(ReflectionClass $class, string $attributeClass): ?object
    {
        $attributes = $class->getAttributes($attributeClass);

        if (empty($attributes)) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * Get an attribute instance from a constant.
     *
     * @template T of object
     *
     * @param  class-string<T>  $attributeClass
     * @return T|null
     */
    private function getConstantAttribute(ReflectionClassConstant $constant, string $attributeClass): ?object
    {
        $attributes = $constant->getAttributes($attributeClass);

        if (empty($attributes)) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * Get all capability names from a constant's Capability attributes.
     *
     * @return array<string>
     */
    private function getCapabilityNames(ReflectionClassConstant $constant): array
    {
        $attributes = $constant->getAttributes(CapabilityAttribute::class);

        return array_map(
            fn ($attr) => $attr->newInstance()->name,
            $attributes
        );
    }

    /**
     * Get all capability names from a class's Capability attributes.
     *
     * @param  ReflectionClass<object>  $class
     * @return array<string>
     */
    private function getClassCapabilityNames(ReflectionClass $class): array
    {
        $attributes = $class->getAttributes(CapabilityAttribute::class);

        return array_map(
            fn ($attr) => $attr->newInstance()->name,
            $attributes
        );
    }
}
