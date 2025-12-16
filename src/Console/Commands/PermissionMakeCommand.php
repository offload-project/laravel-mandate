<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;

final class PermissionMakeCommand extends GeneratorCommand
{
    protected $signature = 'mandate:permission
        {name : The name of the permission class (e.g., UserPermissions)}
        {--set= : The permission set name (e.g., users)}';

    protected $description = 'Create a new permission class';

    protected $type = 'Permission Class';

    protected function getStub(): string
    {
        $customStub = base_path('stubs/mandate/permission.stub');

        if (file_exists($customStub)) {
            return $customStub;
        }

        return __DIR__.'/../../../stubs/permission.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        $directories = config('mandate.permission_directories', []);

        if (! empty($directories)) {
            return array_values($directories)[0];
        }

        return $rootNamespace.'\\Permissions';
    }

    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        $className = class_basename($name);

        // Determine set name
        /** @var string|null $set */
        $set = $this->option('set');
        if (! $set) {
            // UserPermissions -> users
            $set = Str::plural(Str::kebab(Str::replaceLast('Permissions', '', $className)));
        }

        $stub = str_replace('{{ set }}', $set, $stub);

        // Generate example permission values
        $stub = str_replace('{{ resource }}', Str::singular($set), $stub);

        return $stub;
    }

    protected function getPath($name): string
    {
        $directories = config('mandate.permission_directories', []);

        if (! empty($directories)) {
            $basePath = array_keys($directories)[0];
            $namespace = array_values($directories)[0];

            $relativeName = Str::after($name, $namespace.'\\');

            return $basePath.'/'.str_replace('\\', '/', $relativeName).'.php';
        }

        return parent::getPath($name);
    }
}
