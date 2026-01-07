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
 * Middleware to check for required roles.
 *
 * Usage in routes:
 * - Route::middleware('role:admin')
 * - Route::middleware('role:admin|editor') // OR logic
 * - Route::middleware('role:admin,web') // with specific guard
 */
final class RoleMiddleware
{
    /**
     * Generate middleware string for use in routes.
     *
     * @param  string|array<string>  $roles
     */
    public static function using(string|array $roles, ?string $guard = null): string
    {
        $roleString = is_array($roles)
            ? implode('|', $roles)
            : $roles;

        if ($guard !== null) {
            return "role:{$roleString},{$guard}";
        }

        return "role:{$roleString}";
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $roles, ?string $guard = null): Response
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
        if (! method_exists($subject, 'hasRole')) {
            throw UnauthorizedException::notEloquentModel();
        }

        $roleList = $this->parseRoles($roles);

        // In debug mode, validate that roles exist to catch typos early
        if (app()->hasDebugModeEnabled()) {
            $this->validateRolesExist($roleList, Guard::getNameForModel($subject));
        }

        // Check if subject has any of the required roles (OR logic)
        foreach ($roleList as $role) {
            if ($subject->hasRole($role)) {
                return $next($request);
            }
        }

        throw UnauthorizedException::forRoles($roleList);
    }

    /**
     * Parse the roles string into an array.
     *
     * @return array<string>
     */
    private function parseRoles(string $roles): array
    {
        return array_map('trim', explode('|', $roles));
    }

    /**
     * Validate that all roles exist (debug mode only).
     *
     * @param  array<string>  $roles
     *
     * @throws RuntimeException
     */
    private function validateRolesExist(array $roles, string $guard): void
    {
        $registrar = app(MandateRegistrar::class);

        foreach ($roles as $role) {
            if (! $registrar->roleExists($role, $guard)) {
                throw new RuntimeException(
                    "Role '{$role}' does not exist for guard '{$guard}'. "
                    .'Create it first or check for typos.'
                );
            }
        }
    }
}
