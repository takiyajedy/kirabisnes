<?php

use App\Mcp\Servers\KiraBisnesServer;
use App\Mcp\Tools\FundBalancesTool;
use App\Models\Setting;
use App\Models\User;
use App\Support\McpToken;
use Database\Seeders\SettingSeeder;
use Illuminate\Testing\TestResponse;

/**
 * The endpoint is the whole surface an AI client reaches. Everything behind
 * it can read and change the books, so the guard gets its own tests.
 */
beforeEach(function () {
    $this->seed(SettingSeeder::class);
    User::factory()->create();
});

function listTools(?string $token): TestResponse
{
    return test()->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ], $token === null ? [] : ['Authorization' => "Bearer {$token}"]);
}

it('refuses a caller with no token', function () {
    McpToken::mint();

    listTools(null)
        ->assertUnauthorized()
        // The package supplies the spec-compliant challenge; an MCP client
        // reads it to tell "wrong token" from "server is broken".
        ->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", error="invalid_token"');
});

it('refuses a caller with the wrong token', function () {
    McpToken::mint();

    listTools('kb_not-the-real-one')->assertUnauthorized();
});

it('refuses everyone when no token has been minted', function () {
    listTools('kb_anything')->assertUnauthorized();
});

it('lets the minted token in', function () {
    listTools(McpToken::mint())->assertOk();
});

it('stops matching an old token once a new one is minted', function () {
    $old = McpToken::mint();
    McpToken::mint();

    listTools($old)->assertUnauthorized();
});

it('refuses every caller after the token is revoked', function () {
    $token = McpToken::mint();
    McpToken::revoke();

    listTools($token)->assertUnauthorized();
});

it('never stores the token itself', function () {
    $token = McpToken::mint();

    $stored = Setting::get('mcp_token_hash');

    expect($stored)->not->toBe($token)
        ->and($stored)->not->toContain($token)
        // SHA-256 hex.
        ->and($stored)->toMatch('/^[a-f0-9]{64}$/');
});

it('signs the owner in so writes are attributed', function () {
    $token = McpToken::mint();

    // Without this, an audited write would land with no user against it.
    KiraBisnesServer::actingAs(User::query()->first())
        ->tool(FundBalancesTool::class)
        ->assertOk();

    listTools($token)->assertOk();
});
