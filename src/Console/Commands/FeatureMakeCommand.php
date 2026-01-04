<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;

final class FeatureMakeCommand extends GeneratorCommand
{
    protected $signature = 'mandate:feature
        {name : The name of the feature class (e.g., DarkMode or BetaDashboard)}
        {--set= : The feature set name (e.g., ui)}';

    protected $description = 'Create a new feature class';

    protected $type = 'Feature Class';

    protected function getStub(): string
    {
        $customStub = base_path('stubs/mandate/feature.stub');

        if (file_exists($customStub)) {
            return $customStub;
        }

        return __DIR__.'/../../../stubs/feature.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        $directories = config('mandate.discovery.features', []);

        if (! empty($directories)) {
            return array_values($directories)[0];
        }

        return $rootNamespace.'\\Features';
    }

    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        $className = class_basename($name);

        // Determine set name
        /** @var string|null $set */
        $set = $this->option('set');
        if (! $set) {
            // DarkMode -> dark-mode, BetaDashboard -> beta-dashboard
            $set = Str::kebab($className);
        }

        $stub = str_replace('{{ set }}', $set, $stub);

        // Generate label from class name
        // DarkMode -> Dark Mode, BetaDashboard -> Beta Dashboard
        $label = Str::headline($className);
        $stub = str_replace('{{ label }}', $label, $stub);

        return $stub;
    }

    protected function getPath($name): string
    {
        $directories = config('mandate.discovery.features', []);

        if (! empty($directories)) {
            $basePath = array_keys($directories)[0];
            $namespace = array_values($directories)[0];

            $relativeName = Str::after($name, $namespace.'\\');

            return $basePath.'/'.str_replace('\\', '/', $relativeName).'.php';
        }

        return parent::getPath($name);
    }
}
