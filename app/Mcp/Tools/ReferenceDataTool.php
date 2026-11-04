<?php

namespace App\Mcp\Tools;

use App\Models\Account;
use App\Models\Category;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The write tools take ids, not names. This is where those ids come from:
 * guessing an account or category id would attach money to the wrong row,
 * and the mistake would be invisible until a report looked wrong.
 */
#[Name('reference_data')]
#[Description('The ids the write tools need: accounts, categories, clients and projects. Call this before recording a transaction or creating an invoice.')]
#[IsReadOnly]
#[IsIdempotent]
class ReferenceDataTool extends Tool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        return Response::structured([
            'accounts' => Account::query()->where('is_active', true)->orderBy('sort_order')
                ->get(['id', 'name', 'type'])
                ->map(fn (Account $a) => ['id' => $a->id, 'name' => $a->name, 'type' => $a->type->value])
                ->all(),

            'categories' => Category::query()->orderBy('type')
                ->get()
                ->map(fn (Category $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'type' => $c->type->value,
                    'tax_deductible_by_default' => (bool) $c->tax_deductible_default,
                ])
                ->all(),

            'clients' => Client::query()->orderBy('name')
                ->get()
                ->map(fn (Client $c) => ['id' => $c->id, 'name' => $c->displayName(), 'email' => $c->email])
                ->all(),

            'projects' => Project::query()->orderByDesc('id')->limit(50)
                ->get(['id', 'name', 'client_id', 'status'])
                ->map(fn (Project $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'client_id' => $p->client_id,
                    'status' => $p->status->value,
                ])
                ->all(),
        ]);
    }
}
