<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\McpToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the MCP endpoint with the single bearer token.
 *
 * The books are one person's, so there is one token rather than a user
 * table lookup. A caller that presents it is acting as the owner, and the
 * owner is signed in for the rest of the request so anything that reads
 * auth()->user() — the audit log, above all — records who did it.
 */
class EnsureMcpToken
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null || ! McpToken::matches($token)) {
            // A bare 401. The package's transport replaces the challenge
            // header with the spec-compliant one the MCP auth flow expects,
            // so setting our own here would only be overwritten.
            return response()->json([
                'error' => 'Invalid or missing MCP token.',
            ], 401);
        }

        $owner = User::query()->oldest('id')->first();

        if ($owner === null) {
            return response()->json([
                'error' => 'No owner account exists yet.',
            ], 503);
        }

        auth()->setUser($owner);

        return $next($request);
    }
}
