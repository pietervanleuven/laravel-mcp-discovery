<?php

use Illuminate\Contracts\Http\Kernel;
use PieterVanLeuven\McpDiscovery\Http\Middleware\AddDiscoveryHeaders;

const BLOG_LINK = '<https://example.com/mcp/blog>; rel="mcp"; title="Blog"';
const CARD_LINK = '<https://example.com/.well-known/mcp-server-card>; rel="service-desc"; type="application/json"';

it('adds nothing by default', function () {
    expect($this->get('/articles/hello', ['User-Agent' => CLAUDE_UA])->headers->has('Link'))->toBeFalse();
});

it('adds Link headers for bots on HTML responses', function () {
    withConfig(['mcp-discovery.emitters.link_header.enabled' => true]);

    $links = $this->get('/articles/hello', ['User-Agent' => CLAUDE_UA])->headers->all('Link');

    expect($links)->toContain(BLOG_LINK)
        ->toContain('<https://example.com/mcp/shop>; rel="mcp"; title="Shop"')
        ->toContain(CARD_LINK)
        ->not->toContain('<https://example.com/mcp/ops>; rel="mcp"; title="Internal"');
});

it('skips browsers when only bots should get the header', function () {
    withConfig(['mcp-discovery.emitters.link_header.enabled' => true]);

    expect($this->get('/articles/hello', ['User-Agent' => BROWSER_UA])->headers->has('Link'))->toBeFalse();
});

it('can add the header for everyone', function () {
    withConfig([
        'mcp-discovery.emitters.link_header.enabled' => true,
        'mcp-discovery.emitters.link_header.only' => 'all',
    ]);

    expect($this->get('/articles/hello', ['User-Agent' => BROWSER_UA])->headers->all('Link'))->toContain(BLOG_LINK);
});

it('skips non-HTML responses', function () {
    withConfig([
        'mcp-discovery.emitters.link_header.enabled' => true,
        'mcp-discovery.emitters.link_header.only' => 'all',
    ]);

    expect($this->get('/api/data')->headers->has('Link'))->toBeFalse();
});

it('always points markdown clients at MCP when the markdown hint is on', function () {
    withConfig(['mcp-discovery.emitters.markdown_hint.enabled' => true]);

    $response = $this->get('/api/data', ['Accept' => 'text/markdown', 'User-Agent' => BROWSER_UA]);

    expect($response->headers->all('Link'))->toContain(BLOG_LINK)
        ->and($response->headers->get('Vary'))->toContain('Accept');
});

it('does not register the middleware when nothing provides links', function () {
    expect(app(Kernel::class)->getMiddlewareGroups()['web'] ?? [])
        ->not->toContain(AddDiscoveryHeaders::class);
});

it('registers the middleware when an emitter provides links', function () {
    withConfig(['mcp-discovery.emitters.link_header.enabled' => true]);

    expect(app(Kernel::class)->getMiddlewareGroups()['web'])
        ->toContain(AddDiscoveryHeaders::class);
});
