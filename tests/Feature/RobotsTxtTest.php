<?php

use PieterVanLeuven\McpDiscovery\Emitters\RobotsTxtEmitter;
use PieterVanLeuven\McpDiscovery\McpDiscovery;

function robots(): RobotsTxtEmitter
{
    return app(McpDiscovery::class)->emitter('robots_txt');
}

it('adds MCP pointers and content signals without adding rules', function () {
    $robots = robots()->inject("User-agent: *\nDisallow: /admin\n");

    expect($robots)->toBe(<<<'TXT'
        User-agent: *
        Disallow: /admin

        # mcp-discovery:start
        # MCP server (com.example/blog): https://example.com/mcp/blog
        # MCP server card: https://example.com/.well-known/mcp-server-card
        # MCP server (com.example/shop): https://example.com/mcp/shop
        # MCP server card: https://example.com/.well-known/mcp-server-card/shop
        User-agent: *
        Content-Signal: search=yes, ai-input=yes, ai-train=no
        # mcp-discovery:end

        TXT);
});

it('is served as a route in route mode', function () {
    withConfig([
        'mcp-discovery.emitters.robots_txt.enabled' => true,
        'mcp-discovery.emitters.robots_txt.mode' => 'route',
        'mcp-discovery.emitters.robots_txt.content_signals' => ['ai-train' => false],
    ]);

    expect($this->get('/robots.txt')->assertOk()->getContent())
        ->toStartWith("User-agent: *\nDisallow:\n")
        ->toContain('Content-Signal: ai-train=no');
});
