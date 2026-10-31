<?php

namespace App\Http\Middleware;

use App\Support\Registration;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hides Fortify's registration routes once the single owner account exists.
 */
class EnsureRegistrationIsOpen
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isRegistrationRoute = $request->routeIs('register', 'register.store');

        if ($isRegistrationRoute && ! Registration::isOpen()) {
            abort(404);
        }

        return $next($request);
    }
}
