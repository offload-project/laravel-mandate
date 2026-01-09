<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Generate a new capability class for code-first definitions.
 */
#[AsCommand(name: 'mandate:make:capability')]
final class MakeCapabilityCommand extends GeneratorCommand
{
    protected $name = 'mandate:make:capability';

    protected $description = 'Create a new capability class for code-first definitions';

    protected $type = 'Capability';

    protected function getStub(): string
    {
        $customStub = base_path('stubs/mandate/capability.stub');

        if (file_exists($customStub)) {
            return $customStub;
        }

        return __DIR__.'/../../stubs/capability.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\Capabilities';
    }

    /**
     * @return array<array<mixed>>
     */
    protected function getOptions(): array
    {
        return [
            ['guard', 'g', InputOption::VALUE_OPTIONAL, 'The guard to use for the capability class', 'web'],
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the capability already exists'],
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
        $capabilityName = Str::kebab(Str::replaceLast('Capabilities', '', $className));
        /** @var string $guard */
        $guard = $this->option('guard') ?? 'web';

        return str_replace(
            ['{{ capability }}', '{{ guard }}'],
            [$capabilityName, $guard],
            $stub
        );
    }
}
