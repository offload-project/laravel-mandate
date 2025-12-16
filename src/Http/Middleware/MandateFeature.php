<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to check if a feature is active.
 *
 * Usage in routes:
 *   Route::get('/export', ...)->middleware('mandate.feature:App\Features\ExportFeature');
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

        if (! Feature::for($user)->active($feature)) {
            abort(403, 'This feature is not available.');
        }

        return $next($request);
    }
}
