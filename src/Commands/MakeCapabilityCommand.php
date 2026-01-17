<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use OffloadProject\Mandate\Guard;
use OffloadProject\Mandate\Models\Capability;
use OffloadProject\Mandate\Models\Permission;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Create a new capability class or database record.
 *
 * Usage:
 * - php artisan mandate:capability ContentCapabilities (generates class)
 * - php artisan mandate:capability manage-posts --db (creates in database)
 * - php artisan mandate:capability manage-posts --db --permissions=post:view,post:edit
 */
#[AsCommand(name: 'mandate:capability')]
final class MakeCapabilityCommand extends GeneratorCommand
{
    protected $name = 'mandate:capability';

    protected $description = 'Create a new capability class or database record';

    protected $type = 'Capability';

    /** @phpstan-ignore method.childReturnType */
    public function handle(): int
    {
        if ($this->option('db')) {
            return $this->createInDatabase();
        }

        return parent::handle() === false ? self::FAILURE : self::SUCCESS;
    }

    protected function createInDatabase(): int
    {
        if (! config('mandate.capabilities.enabled', false)) {
            $this->components->error(
                'Capabilities feature is not enabled. '
                .'Set mandate.capabilities.enabled to true in your configuration.'
            );

            return self::FAILURE;
        }

        /** @var string $name */
        $name = $this->getNameInput();

        /** @var string|null $guard */
        $guard = $this->option('guard');
        $guard ??= Guard::getDefaultName();

        /** @var string|null $permissionsOption */
        $permissionsOption = $this->option('permissions');

        /** @var class-string<Capability> $capabilityClass */
        $capabilityClass = config('mandate.models.capability', Capability::class);

        $existing = $capabilityClass::query()
            ->where('name', $name)
            ->where('guard', $guard)
            ->first();

        if ($existing) {
            $this->components->warn("Capability '{$name}' already exists for guard '{$guard}'.");
            $capability = $existing;
        } else {
            $capability = $capabilityClass::create([
                'name' => $name,
                'guard' => $guard,
            ]);

            $this->components->info("Capability '{$name}' created for guard '{$guard}'.");
        }

        if ($permissionsOption !== null) {
            $permissions = array_map('trim', explode(',', $permissionsOption));
            $assignedCount = 0;

            /** @var class-string<Permission> $permissionClass */
            $permissionClass = config('mandate.models.permission', Permission::class);

            foreach ($permissions as $permissionName) {
                $permission = $permissionClass::findOrCreate($permissionName, $guard);

                if (! $capability->hasPermission($permission)) {
                    $capability->grantPermission($permission);
                    $assignedCount++;
                }
            }

            if ($assignedCount > 0) {
                $this->components->info("Assigned {$assignedCount} permission(s) to capability '{$name}'.");
            }
        }

        return self::SUCCESS;
    }

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
            ['guard', 'g', InputOption::VALUE_OPTIONAL, 'The guard to use', 'web'],
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if it already exists'],
            ['db', null, InputOption::VALUE_NONE, 'Create a database record instead of a class file'],
            ['permissions', null, InputOption::VALUE_OPTIONAL, 'Comma-separated permissions to assign (with --db)'],
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
