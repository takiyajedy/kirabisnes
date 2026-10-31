<?php

namespace App\Http\Middleware;

use App\Enums\Appearance;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve the colour scheme for the request, in this order of precedence:
 *   1. users.appearance (when authenticated, so the choice syncs across devices)
 *   2. the "appearance" cookie (avoids a flash of the wrong theme on first paint)
 *   3. "system"
 */
class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        View::share('appearance', $this->resolve($request));

        return $next($request);
    }

    private function resolve(Request $request): string
    {
        $user = $request->user();

        if ($user?->appearance !== null) {
            return $user->appearance->value;
        }

        $cookie = $request->cookie('appearance');

        if (is_string($cookie) && in_array($cookie, Appearance::values(), true)) {
            return $cookie;
        }

        return Appearance::System->value;
    }
}
