<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;

final class RoleMakeCommand extends GeneratorCommand
{
    protected $signature = 'mandate:role
        {name : The name of the role class (e.g., SystemRoles or UserRoles)}
        {--set= : The role set name (e.g., system)}';

    protected $description = 'Create a new role class';

    protected $type = 'Role Class';

    protected function getStub(): string
    {
        $customStub = base_path('stubs/mandate/role.stub');

        if (file_exists($customStub)) {
            return $customStub;
        }

        return __DIR__.'/../../../stubs/role.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        $directories = config('mandate.role_directories', []);

        if (! empty($directories)) {
            return array_values($directories)[0];
        }

        return $rootNamespace.'\\Roles';
    }

    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        $className = class_basename($name);

        // Determine set name
        /** @var string|null $set */
        $set = $this->option('set');
        if (! $set) {
            // SystemRoles -> system, UserRoles -> user, Roles -> default
            $baseName = Str::replaceLast('Roles', '', $className);
            $set = $baseName ? Str::kebab($baseName) : 'default';
        }

        $stub = str_replace('{{ set }}', $set, $stub);

        return $stub;
    }

    protected function getPath($name): string
    {
        $directories = config('mandate.role_directories', []);

        if (! empty($directories)) {
            $basePath = array_keys($directories)[0];
            $namespace = array_values($directories)[0];

            $relativeName = Str::after($name, $namespace.'\\');

            return $basePath.'/'.str_replace('\\', '/', $relativeName).'.php';
        }

        return parent::getPath($name);
    }
}
