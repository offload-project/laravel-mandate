<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use OffloadProject\Mandate\Facades\Mandate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to check permissions with feature flag awareness.
 *
 * Usage in routes:
 *   Route::get('/users/export', ...)->middleware('mandate.permission:export users');
 *   Route::get('/users', ...)->middleware('mandate.permission:view users,list users');
 *
 * Usage with constant (in route service provider or controller):
 *   $this->middleware(MandatePermission::using(UserPermissions::EXPORT));
 */
final class MandatePermission
{
    /**
     * Create middleware string for a permission.
     */
    public static function using(string ...$permissions): string
    {
        return 'mandate.permission:'.implode(',', $permissions);
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Unauthorized.');
        }

        foreach ($permissions as $permission) {
            if (Mandate::can($user, $permission)) {
                return $next($request);
            }
        }

        abort(403, 'You do not have the required permission.');
    }
}
