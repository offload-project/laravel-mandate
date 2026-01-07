<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use OffloadProject\Mandate\Guard;
use OffloadProject\Mandate\Models\Role;

/**
 * Artisan command to assign a role to a subject.
 *
 * Usage:
 * - php artisan mandate:assign-role 1 admin
 * - php artisan mandate:assign-role 1 admin --guard=api
 * - php artisan mandate:assign-role 1 admin --model="App\Models\User"
 */
final class AssignRoleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mandate:assign-role
                            {subject : The subject ID to assign the role to}
                            {role : The role name to assign}
                            {--guard= : The guard to use}
                            {--model= : The model class (defaults to guard\'s configured model)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign a role to a subject';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /** @var string $subjectId */
        $subjectId = $this->argument('subject');

        /** @var string $roleName */
        $roleName = $this->argument('role');

        /** @var string|null $guard */
        $guard = $this->option('guard');
        $guard ??= Guard::getDefaultName();

        /** @var string|null $modelClass */
        $modelClass = $this->option('model');
        $modelClass ??= Guard::getModelClassForGuard($guard);

        if ($modelClass === null) {
            $this->components->error(
                "Could not determine model class for guard '{$guard}'. "
                .'Use --model option to specify the model class.'
            );

            return self::FAILURE;
        }

        // Find the subject
        if (! class_exists($modelClass)) {
            $this->components->error("Model class '{$modelClass}' does not exist.");

            return self::FAILURE;
        }

        /** @var Model|null $subject */
        $subject = $modelClass::find($subjectId);

        if ($subject === null) {
            $this->components->error("Subject with ID '{$subjectId}' not found.");

            return self::FAILURE;
        }

        // Check if subject model has the required trait
        if (! method_exists($subject, 'assignRole')) {
            $this->components->error(
                "Model '{$modelClass}' does not use the HasRoles trait."
            );

            return self::FAILURE;
        }

        // Find the role
        /** @var class-string<Role> $roleClass */
        $roleClass = config('mandate.models.role', Role::class);

        $role = $roleClass::query()
            ->where('name', $roleName)
            ->where('guard', $guard)
            ->first();

        if ($role === null) {
            $this->components->error(
                "Role '{$roleName}' not found for guard '{$guard}'. "
                .'Create it first with: php artisan mandate:role '.$roleName
            );

            return self::FAILURE;
        }

        // Check if subject already has the role
        if ($subject->hasRole($roleName)) {
            $this->components->warn(
                "Subject #{$subjectId} already has role '{$roleName}'."
            );

            return self::SUCCESS;
        }

        // Assign the role
        $subject->assignRole($role);

        $this->components->info(
            "Role '{$roleName}' assigned to subject #{$subjectId}."
        );

        return self::SUCCESS;
    }
}
