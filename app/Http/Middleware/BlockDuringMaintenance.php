<?php

namespace App\Http\Middleware;

use App\Services\ApplicationSettings;
use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockDuringMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        $settings = app(ApplicationSettings::class);

        if (! $settings->maintenanceModeEnabled()) {
            return $next($request);
        }

        if ($request->user()?->is_admin) {
            return $next($request);
        }

        if ($this->isAllowedDuringMaintenance($request)) {
            return $next($request);
        }

        $message = $settings->maintenanceMessage();

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 503);
        }

        return response()->view('maintenance', [
            'message' => $message,
        ], 503);
    }

    private function isAllowedDuringMaintenance(Request $request): bool
    {
        if ($this->isAssetRequest($request)) {
            return true;
        }

        if ($request->routeIs(
            'login',
            'logout',
            'password.request',
            'password.email',
            'password.reset',
            'password.store',
        )) {
            return true;
        }

        if ($request->is('login') && $request->isMethod('post')) {
            return true;
        }

        if ($request->is('/') && $request->isMethod('get') && $request->user() === null) {
            return true;
        }

        return $request->user() === null && $this->routeRequiresAuthentication($request);
    }

    private function isAssetRequest(Request $request): bool
    {
        return $request->is(
            'build/*',
            'css/*',
            'favicon.ico',
            'images/*',
            'js/*',
            'logo.png',
        );
    }

    private function routeRequiresAuthentication(Request $request): bool
    {
        $route = $request->route();

        if ($route === null) {
            return false;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if ($middleware === 'auth'
                || str_starts_with($middleware, 'auth:')
                || $middleware === Authenticate::class) {
                return true;
            }
        }

        return false;
    }
}
