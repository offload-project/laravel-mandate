<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OffloadProject\Mandate\Exceptions\UnauthorizedException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to check for required role OR permission.
 *
 * Usage in routes:
 * - Route::middleware('role_or_permission:admin|article:edit')
 * - Route::middleware('role_or_permission:admin|editor|article:edit|article:delete')
 * - Route::middleware('role_or_permission:admin|article:edit,web') // with specific guard
 *
 * This middleware will pass if the subject has ANY of the specified roles or permissions.
 */
final class RoleOrPermissionMiddleware
{
    /**
     * Generate middleware string for use in routes.
     *
     * @param  string|array<string>  $rolesOrPermissions
     */
    public static function using(string|array $rolesOrPermissions, ?string $guard = null): string
    {
        $itemString = is_array($rolesOrPermissions)
            ? implode('|', $rolesOrPermissions)
            : $rolesOrPermissions;

        if ($guard !== null) {
            return "role_or_permission:{$itemString},{$guard}";
        }

        return "role_or_permission:{$itemString}";
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $rolesOrPermissions, ?string $guard = null): Response
    {
        $guard ??= Auth::getDefaultDriver();
        $subject = Auth::guard($guard)->user();

        if ($subject === null) {
            throw UnauthorizedException::notLoggedIn();
        }

        if (! $subject instanceof Model) {
            throw UnauthorizedException::notEloquentModel();
        }

        // Check if subject model has the required traits
        $hasRolesMethod = method_exists($subject, 'hasRole');
        $hasPermissionsMethod = method_exists($subject, 'hasPermission');

        if (! $hasRolesMethod && ! $hasPermissionsMethod) {
            throw UnauthorizedException::notEloquentModel();
        }

        $items = $this->parseItems($rolesOrPermissions);
        $roles = [];
        $permissions = [];

        // Check each item
        foreach ($items as $item) {
            // Try as role first (if subject has HasRoles trait)
            if ($hasRolesMethod && $subject->hasRole($item)) {
                return $next($request);
            }

            // Try as permission (if subject has HasPermissions trait)
            if ($hasPermissionsMethod && $subject->hasPermission($item)) {
                return $next($request);
            }

            // Categorize for error message (items with : are likely permissions)
            if (str_contains($item, ':')) {
                $permissions[] = $item;
            } else {
                $roles[] = $item;
            }
        }

        throw UnauthorizedException::forRolesOrPermissions($roles, $permissions);
    }

    /**
     * Parse the roles/permissions string into an array.
     *
     * @return array<string>
     */
    private function parseItems(string $rolesOrPermissions): array
    {
        return array_map('trim', explode('|', $rolesOrPermissions));
    }
}
