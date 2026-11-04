<?php

namespace App\Console\Commands;

use App\Support\McpToken;
use Illuminate\Console\Command;

class McpTokenCommand extends Command
{
    protected $signature = 'mcp:token {--revoke : Remove the token instead of minting one}';

    protected $description = 'Mint (or revoke) the bearer token an AI client uses to reach the MCP server';

    public function handle(): int
    {
        if ($this->option('revoke')) {
            McpToken::revoke();

            $this->components->info('MCP token revoked. The endpoint now refuses every caller.');

            return self::SUCCESS;
        }

        if (McpToken::exists() && ! $this->confirm('A token already exists. Minting a new one breaks the old one. Continue?', false)) {
            return self::SUCCESS;
        }

        $token = McpToken::mint();

        $this->newLine();
        $this->components->info('MCP token minted. It is shown once and cannot be read back.');
        $this->newLine();
        $this->line('  '.$token);
        $this->newLine();
        $this->components->warn('Anyone holding this token can read and change the books. Treat it like a password.');

        return self::SUCCESS;
    }
}
