<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use OffloadProject\Mandate\Facades\Mandate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to check roles with feature flag awareness.
 *
 * Usage in routes:
 *   Route::get('/admin', ...)->middleware('mandate.role:admin');
 *   Route::get('/dashboard', ...)->middleware('mandate.role:admin,editor');
 *
 * Usage with constant (in route service provider or controller):
 *   $this->middleware(MandateRole::using(SystemRoles::ADMINISTRATOR));
 */
final class MandateRole
{
    /**
     * Create middleware string for a role.
     */
    public static function using(string ...$roles): string
    {
        return 'mandate.role:'.implode(',', $roles);
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Unauthorized.');
        }

        foreach ($roles as $role) {
            if (Mandate::hasRole($user, $role)) {
                return $next($request);
            }
        }

        abort(403, 'You do not have the required role.');
    }
}
