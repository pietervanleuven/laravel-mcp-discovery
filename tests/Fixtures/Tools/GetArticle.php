<?php

namespace PieterVanLeuven\McpDiscovery\Tests\Fixtures\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Title('Get article')]
#[Description('Fetch one article as Markdown.')]
class GetArticle extends Tool
{
    public function handle(Request $request): Response
    {
        return Response::text('# Hello');
    }
}
