<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use OffloadProject\Mandate\Guard;
use OffloadProject\Mandate\Models\Permission;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Create a new permission class or database record.
 *
 * Usage:
 * - php artisan mandate:permission ArticlePermissions (generates class)
 * - php artisan mandate:permission article:view --db (creates in database)
 */
#[AsCommand(name: 'mandate:permission')]
final class MakePermissionCommand extends GeneratorCommand
{
    protected $name = 'mandate:permission';

    protected $description = 'Create a new permission class or database record';

    protected $type = 'Permission';

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

        /** @var class-string<Permission> $permissionClass */
        $permissionClass = config('mandate.models.permission', Permission::class);

        $existing = $permissionClass::query()
            ->where('name', $name)
            ->where('guard', $guard)
            ->first();

        if ($existing) {
            $this->components->warn("Permission '{$name}' already exists for guard '{$guard}'.");

            return self::SUCCESS;
        }

        $permissionClass::create([
            'name' => $name,
            'guard' => $guard,
        ]);

        $this->components->info("Permission '{$name}' created for guard '{$guard}'.");

        return self::SUCCESS;
    }

    protected function getStub(): string
    {
        $customStub = base_path('stubs/mandate/permission.stub');

        if (file_exists($customStub)) {
            return $customStub;
        }

        return __DIR__ . '/../../stubs/permission.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        $configuredPath = config('mandate.code_first.paths.permissions');

        if ($configuredPath) {
            return $this->pathToNamespace($configuredPath);
        }

        return $rootNamespace . '\\Permissions';
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
