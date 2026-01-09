<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Generate a new role class for code-first definitions.
 */
#[AsCommand(name: 'mandate:make:role')]
final class MakeRoleCommand extends GeneratorCommand
{
    protected $name = 'mandate:make:role';

    protected $description = 'Create a new role class for code-first definitions';

    protected $type = 'Role';

    protected function getStub(): string
    {
        $customStub = base_path('stubs/mandate/role.stub');

        if (file_exists($customStub)) {
            return $customStub;
        }

        return __DIR__.'/../../stubs/role.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\Roles';
    }

    /**
     * @return array<array<mixed>>
     */
    protected function getOptions(): array
    {
        return [
            ['guard', 'g', InputOption::VALUE_OPTIONAL, 'The guard to use for the role class', 'web'],
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the role already exists'],
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
        $roleName = Str::snake(Str::replaceLast('Roles', '', $className));
        /** @var string $guard */
        $guard = $this->option('guard') ?? 'web';

        return str_replace(
            ['{{ role }}', '{{ guard }}'],
            [$roleName, $guard],
            $stub
        );
    }
}
