<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OffloadProject\Mandate\Exceptions\UnauthorizedException;
use OffloadProject\Mandate\Guard;
use OffloadProject\Mandate\MandateRegistrar;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to check for required permissions.
 *
 * Usage in routes:
 * - Route::middleware('permission:article:edit')
 * - Route::middleware('permission:article:edit|article:delete') // OR logic
 * - Route::middleware('permission:article:edit,web') // with specific guard
 */
final class PermissionMiddleware
{
    /**
     * Generate middleware string for use in routes.
     *
     * @param  string|array<string>  $permissions
     */
    public static function using(string|array $permissions, ?string $guard = null): string
    {
        $permissionString = is_array($permissions)
            ? implode('|', $permissions)
            : $permissions;

        if ($guard !== null) {
            return "permission:{$permissionString},{$guard}";
        }

        return "permission:{$permissionString}";
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $permissions, ?string $guard = null): Response
    {
        $guard ??= Auth::getDefaultDriver();
        $subject = Auth::guard($guard)->user();

        if ($subject === null) {
            throw UnauthorizedException::notLoggedIn();
        }

        if (! $subject instanceof Model) {
            throw UnauthorizedException::notEloquentModel();
        }

        // Check if subject model has the required trait
        if (! method_exists($subject, 'hasPermission')) {
            throw UnauthorizedException::notEloquentModel();
        }

        $permissionList = $this->parsePermissions($permissions);

        // In debug mode, validate that permissions exist to catch typos early
        if (app()->hasDebugModeEnabled()) {
            $this->validatePermissionsExist($permissionList, Guard::getNameForModel($subject));
        }

        // Check if subject has any of the required permissions (OR logic)
        foreach ($permissionList as $permission) {
            if ($subject->hasPermission($permission)) {
                return $next($request);
            }
        }

        throw UnauthorizedException::forPermissions($permissionList);
    }

    /**
     * Parse the permissions string into an array.
     *
     * @return array<string>
     */
    private function parsePermissions(string $permissions): array
    {
        return array_map('trim', explode('|', $permissions));
    }

    /**
     * Validate that all permissions exist (debug mode only).
     *
     * @param  array<string>  $permissions
     *
     * @throws RuntimeException
     */
    private function validatePermissionsExist(array $permissions, string $guard): void
    {
        $registrar = app(MandateRegistrar::class);

        foreach ($permissions as $permission) {
            if (! $registrar->permissionExists($permission, $guard)) {
                throw new RuntimeException(
                    "Permission '{$permission}' does not exist for guard '{$guard}'. "
                    .'Create it first or check for typos.'
                );
            }
        }
    }
}
