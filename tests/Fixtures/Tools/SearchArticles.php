<?php

namespace PieterVanLeuven\McpDiscovery\Tests\Fixtures\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Full-text search over published articles.')]
class SearchArticles extends Tool
{
    public function handle(Request $request): Response
    {
        return Response::text('[]');
    }

    public function schema(JsonSchema $schema): array
    {
        return ['query' => $schema->string()->required()];
    }
}
