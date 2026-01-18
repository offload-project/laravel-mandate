<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use OffloadProject\Mandate\Guard;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Create a new role class or database record.
 *
 * Usage:
 * - php artisan mandate:role SystemRoles (generates class)
 * - php artisan mandate:role admin --db (creates in database)
 * - php artisan mandate:role admin --db --permissions=article:view,article:edit
 */
#[AsCommand(name: 'mandate:role')]
final class MakeRoleCommand extends GeneratorCommand
{
    protected $name = 'mandate:role';

    protected $description = 'Create a new role class or database record';

    protected $type = 'Role';

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
        /** @var string $name */
        $name = $this->getNameInput();

        /** @var string|null $guard */
        $guard = $this->option('guard');
        $guard ??= Guard::getDefaultName();

        /** @var string|null $permissionsOption */
        $permissionsOption = $this->option('permissions');

        /** @var class-string<Role> $roleClass */
        $roleClass = config('mandate.models.role', Role::class);

        $existing = $roleClass::query()
            ->where('name', $name)
            ->where('guard', $guard)
            ->first();

        if ($existing) {
            $this->components->warn("Role '{$name}' already exists for guard '{$guard}'.");
            $role = $existing;
        } else {
            $role = $roleClass::create([
                'name' => $name,
                'guard' => $guard,
            ]);

            $this->components->info("Role '{$name}' created for guard '{$guard}'.");
        }

        if ($permissionsOption !== null) {
            $permissions = array_filter(array_map('trim', explode(',', $permissionsOption)));
            $assignedCount = 0;

            /** @var class-string<Permission> $permissionClass */
            $permissionClass = config('mandate.models.permission', Permission::class);

            foreach ($permissions as $permissionName) {
                $permission = $permissionClass::findOrCreate($permissionName, $guard);

                if (!$role->hasPermission($permission)) {
                    $role->grantPermission($permission);
                    $assignedCount++;
                }
            }

            if ($assignedCount > 0) {
                $this->components->info("Assigned {$assignedCount} permission(s) to role '{$name}'.");
            }
        }

        return self::SUCCESS;
    }

    protected function getStub(): string
    {
        $customStub = base_path('stubs/mandate/role.stub');

        if (file_exists($customStub)) {
            return $customStub;
        }

        return __DIR__ . '/../../stubs/role.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        $configuredPath = config('mandate.code_first.paths.roles');

        if ($configuredPath) {
            return $this->pathToNamespace($configuredPath);
        }

        return $rootNamespace . '\\Roles';
    }

    /**
     * Convert a filesystem path to a PSR-4 namespace.
     */
    protected function pathToNamespace(string $path): string
    {
        $appPath = $this->laravel->basePath('app');
        $relativePath = str_replace($appPath, '', $path);
        $relativePath = trim($relativePath, DIRECTORY_SEPARATOR);

        $namespace = str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);

        return $this->laravel->getNamespace() . $namespace;
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
     * @param string $stub
     * @param string $name
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
