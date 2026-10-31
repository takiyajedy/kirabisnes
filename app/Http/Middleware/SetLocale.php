<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve the UI language for the request, in this order of precedence:
 *   1. users.locale (when authenticated)
 *   2. the "locale" cookie (guest pages such as login)
 *   3. the Accept-Language header (ms / en only)
 *   4. config('app.locale')
 */
class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }

    private function resolve(Request $request): string
    {
        $user = $request->user();

        if ($user?->locale !== null) {
            return $user->locale->value;
        }

        $cookie = $request->cookie('locale');

        if (is_string($cookie) && in_array($cookie, Locale::values(), true)) {
            return $cookie;
        }

        $preferred = $request->getPreferredLanguage(Locale::values());

        if (is_string($preferred) && in_array($preferred, Locale::values(), true)) {
            return $preferred;
        }

        return config('app.locale');
    }
}
