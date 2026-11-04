<?php

use App\Http\Middleware\EnsureMcpToken;
use App\Mcp\Servers\KiraBisnesServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP Server
|--------------------------------------------------------------------------
|
| The books, exposed to an AI client. Guarded by a single bearer token
| rather than a user table: these are one person's accounts, and the
| token stands for that person. Mint one with `php artisan mcp:token`.
|
| Throttled because a tool loop that goes wrong should run out of requests
| long before it runs out of damage it can do.
|
*/

Mcp::web('mcp', KiraBisnesServer::class)
    ->middleware([EnsureMcpToken::class, 'throttle:60,1']);
