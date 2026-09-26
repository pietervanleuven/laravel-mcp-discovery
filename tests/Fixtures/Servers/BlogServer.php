<?php

namespace PieterVanLeuven\McpDiscovery\Tests\Fixtures\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use PieterVanLeuven\McpDiscovery\Attributes\Discoverable;
use PieterVanLeuven\McpDiscovery\Tests\Fixtures\Tools\GetArticle;
use PieterVanLeuven\McpDiscovery\Tests\Fixtures\Tools\SearchArticles;

#[Name('Blog')]
#[Version('1.0.0')]
#[Instructions('Search and read articles from example.com.')]
#[Discoverable(name: 'com.example/blog', primary: true)]
class BlogServer extends Server
{
    protected array $tools = [
        SearchArticles::class,
        GetArticle::class,
    ];
}
