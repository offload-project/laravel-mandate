<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to check if a feature is active.
 *
 * Usage in routes:
 *   Route::get('/export', ...)->middleware('mandate.feature:App\Features\ExportFeature');
 *   Route::get('/beta', ...)->middleware('mandate.feature:beta-dashboard');
 */
final class MandateFeature
{
    /**
     * Create middleware string for a feature class.
     */
    public static function using(string $featureClass): string
    {
        return 'mandate.feature:'.$featureClass;
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Unauthorized.');
        }

        // Check using the model's hasAccess if it uses UsesFeatures trait
        if (method_exists($user, 'hasAccess')) {
            if (! $user->hasAccess($feature)) {
                abort(403, 'This feature is not available.');
            }

            return $next($request);
        }

        // Fall back to Pennant if available
        if (class_exists(\Laravel\Pennant\Feature::class)) {
            if (! \Laravel\Pennant\Feature::for($user)->active($feature)) {
                abort(403, 'This feature is not available.');
            }

            return $next($request);
        }

        // If no feature checking is available, deny by default
        abort(403, 'This feature is not available.');
    }
}
