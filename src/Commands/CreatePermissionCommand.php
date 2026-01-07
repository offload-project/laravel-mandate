<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Commands;

use Illuminate\Console\Command;
use OffloadProject\Mandate\Guard;
use OffloadProject\Mandate\Models\Permission;

/**
 * Artisan command to create a new permission.
 *
 * Usage:
 * - php artisan mandate:permission article:view
 * - php artisan mandate:permission article:edit --guard=api
 */
final class CreatePermissionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mandate:permission
                            {name : The permission name (e.g., article:view)}
                            {--guard= : The guard to create the permission for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new permission';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /** @var string $name */
        $name = $this->argument('name');

        /** @var string|null $guard */
        $guard = $this->option('guard');
        $guard ??= Guard::getDefaultName();

        /** @var class-string<Permission> $permissionClass */
        $permissionClass = config('mandate.models.permission', Permission::class);

        // Check if permission already exists
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
}
