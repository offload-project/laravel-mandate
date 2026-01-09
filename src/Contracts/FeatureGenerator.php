<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Contracts;

/**
 * Contract for generating feature class files.
 *
 * External packages (e.g., Flagged) can implement this contract
 * to provide their own feature class generator.
 */
interface FeatureGenerator
{
    /**
     * Generate a feature class file.
     *
     * @param  string  $name  The feature name
     * @param  array<string, mixed>  $options  Additional options (guard, label, description, etc.)
     * @return string The path to the generated file
     */
    public function generate(string $name, array $options = []): string;
}
