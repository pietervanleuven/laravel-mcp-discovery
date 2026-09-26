<?php

use Illuminate\Foundation\Auth\User;
use PieterVanLeuven\McpDiscovery\Emitters\ServerCardEmitter;

it('serves the primary server card', function () {
    $this->getJson('/.well-known/mcp-server-card')
        ->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertHeader('Cache-Control', 'max-age=3600, public')
        ->assertExactJson([
            '$schema' => ServerCardEmitter::SCHEMA,
            'name' => 'com.example/blog',
            'version' => '1.0.0',
            'title' => 'Blog',
            'description' => 'Search and read articles from example.com.',
            'websiteUrl' => 'https://example.com',
            'remotes' => [[
                'type' => 'streamable-http',
                'url' => 'https://example.com/mcp/blog',
                'supportedProtocolVersions' => ['2026-07-28'],
            ]],
        ]);
});

it('serves a card per server key', function () {
    $this->getJson('/.well-known/mcp-server-card/shop')
        ->assertOk()
        ->assertJsonPath('name', 'com.example/shop')
        ->assertJsonPath('remotes.0.url', 'https://example.com/mcp/shop');
});

it('advertises the authorization header for bearer-token servers', function () {
    $this->getJson('/.well-known/mcp-server-card/shop')
        ->assertJsonPath('remotes.0.headers.0.name', 'Authorization')
        ->assertJsonPath('remotes.0.headers.0.isSecret', true);
});

it('returns 404 for unknown servers', function () {
    $this->getJson('/.well-known/mcp-server-card/nope')->assertNotFound();
});

it('hides non-public servers from guests', function () {
    $this->getJson('/.well-known/mcp-server-card/ops')->assertNotFound();
});

it('shows non-public servers to authenticated users without public caching', function () {
    $this->actingAs(new User)
        ->getJson('/.well-known/mcp-server-card/ops')
        ->assertOk()
        ->assertJsonPath('name', 'com.example/ops')
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('can include tools, resources and prompts', function () {
    withConfig(['mcp-discovery.emitters.server_card.include_capabilities' => true]);

    $this->getJson('/.well-known/mcp-server-card')
        ->assertJsonPath('tools.0.name', 'search-articles')
        ->assertJsonPath('tools.1.title', 'Get article')
        ->assertJsonPath('resources', [])
        ->assertJsonStructure(['capabilities' => ['tools']]);
});

it('serves an index of every public card when configured', function () {
    withConfig(['mcp-discovery.emitters.server_card.index_path' => '.well-known/mcp/server-cards.json']);

    $this->getJson('/.well-known/mcp/server-cards.json')
        ->assertOk()
        ->assertJsonCount(2, 'servers')
        ->assertJsonPath('servers.0._links.self', 'https://example.com/.well-known/mcp-server-card')
        ->assertJsonPath('servers.1._links.self', 'https://example.com/.well-known/mcp-server-card/shop');
});

it('uses a configurable path', function () {
    withConfig(['mcp-discovery.emitters.server_card.path' => '.well-known/mcp/server-card.json']);

    $this->getJson('/.well-known/mcp/server-card.json')->assertOk();
    $this->getJson('/.well-known/mcp-server-card')->assertNotFound();
});

it('registers no routes when disabled', function () {
    withConfig(['mcp-discovery.emitters.server_card.enabled' => false]);

    $this->getJson('/.well-known/mcp-server-card')->assertNotFound();
});

it('does not trust the Host header for urls', function () {
    $this->getJson('http://evil.test/.well-known/mcp-server-card')
        ->assertJsonPath('remotes.0.url', 'https://example.com/mcp/blog');
});
