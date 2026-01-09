<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Generate a new permission class for code-first definitions.
 */
#[AsCommand(name: 'mandate:make:permission')]
final class MakePermissionCommand extends GeneratorCommand
{
    protected $name = 'mandate:make:permission';

    protected $description = 'Create a new permission class for code-first definitions';

    protected $type = 'Permission';

    protected function getStub(): string
    {
        $customStub = base_path('stubs/mandate/permission.stub');

        if (file_exists($customStub)) {
            return $customStub;
        }

        return __DIR__.'/../../stubs/permission.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\Permissions';
    }

    /**
     * @return array<array<mixed>>
     */
    protected function getOptions(): array
    {
        return [
            ['guard', 'g', InputOption::VALUE_OPTIONAL, 'The guard to use for the permission class', 'web'],
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the permission already exists'],
        ];
    }

    /**
     * @param  string  $stub
     * @param  string  $name
     */
    protected function replaceClass($stub, $name): string
    {
        $stub = parent::replaceClass($stub, $name);

        $className = class_basename($name);
        $resourceName = Str::snake(Str::replaceLast('Permissions', '', $className));
        /** @var string $guard */
        $guard = $this->option('guard') ?? 'web';

        return str_replace(
            ['{{ resource }}', '{{ guard }}'],
            [$resourceName, $guard],
            $stub
        );
    }
}
