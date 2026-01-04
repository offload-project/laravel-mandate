<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use OffloadProject\Mandate\Support\MandateUI;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to share auth data with Inertia.
 *
 * Usage:
 *   // In your HandleInertiaRequests middleware:
 *   public function share(Request $request): array
 *   {
 *       return array_merge(parent::share($request), [
 *           'auth' => app(MandateUI::class)->auth($request->user()),
 *       ]);
 *   }
 *
 *   // Or use this middleware directly in your route/middleware stack
 */
final class MandateInertiaAuthShare
{
    public function __construct(
        private readonly MandateUI $mandateUI,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if Inertia is available
        if (! class_exists(\Inertia\Inertia::class)) {
            return $next($request);
        }

        \Inertia\Inertia::share('auth', function () use ($request) {
            $user = $request->user();

            if (! $user) {
                return null;
            }

            return $this->mandateUI->auth($user);
        });

        return $next($request);
    }
}
