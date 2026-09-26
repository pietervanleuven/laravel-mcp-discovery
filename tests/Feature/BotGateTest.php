<?php

use Illuminate\Contracts\Http\Kernel;
use PieterVanLeuven\McpDiscovery\Http\Middleware\BotGate;

beforeEach(fn () => withConfig(['mcp-discovery.bot_gate.enabled' => true]));

it('points AI bots at the MCP server instead of serving HTML', function () {
    $response = $this->get('/articles/hello', ['User-Agent' => CLAUDE_UA]);

    $response->assertForbidden()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertSee('https://example.com/mcp/blog', false)
        ->assertSee('server card: https://example.com/.well-known/mcp-server-card', false)
        ->assertDontSee('mcp/ops');

    expect($response->headers->all('Link'))->toContain('<https://example.com/mcp/blog>; rel="mcp"; title="Blog"');
});

it('answers in JSON when the bot asks for it', function () {
    $this->getJson('/articles/hello', ['User-Agent' => CLAUDE_UA])
        ->assertForbidden()
        ->assertJsonPath('mcp.0.url', 'https://example.com/mcp/blog')
        ->assertJsonPath('mcp.0.server_card', 'https://example.com/.well-known/mcp-server-card')
        ->assertJsonCount(2, 'mcp');
});

it('can answer 402 Payment Required', function () {
    withConfig(['mcp-discovery.bot_gate.enabled' => true, 'mcp-discovery.bot_gate.response' => 402]);

    $this->get('/articles/hello', ['User-Agent' => CLAUDE_UA])->assertStatus(402);
});

it('can pass bots through with only the Link header', function () {
    withConfig(['mcp-discovery.bot_gate.enabled' => true, 'mcp-discovery.bot_gate.response' => 'pass']);

    $response = $this->get('/articles/hello', ['User-Agent' => CLAUDE_UA])->assertOk()->assertSee('Hello');

    expect($response->headers->all('Link'))->toContain('<https://example.com/mcp/blog>; rel="mcp"; title="Blog"');
});

it('lets browsers through', function () {
    $this->get('/articles/hello', ['User-Agent' => BROWSER_UA])->assertOk()->assertSee('Hello');
});

it('lets allowed search crawlers through', function () {
    $this->get('/articles/hello', ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])
        ->assertOk();
});

it('never gates excepted paths', function () {
    $this->get('/', ['User-Agent' => CLAUDE_UA])->assertOk();
});

it('accepts extra user agents from config', function () {
    withConfig(['mcp-discovery.bot_gate.enabled' => true, 'mcp-discovery.bot_gate.user_agents' => ['AcmeScraper']]);

    $this->get('/articles/hello', ['User-Agent' => 'AcmeScraper/2.0'])->assertForbidden();
});

it('does nothing when there are no public servers', function () {
    withConfig(['mcp-discovery.bot_gate.enabled' => true, 'mcp-discovery.servers' => []]);

    $this->get('/articles/hello', ['User-Agent' => CLAUDE_UA])->assertOk();
});

it('is registered on the web group only when enabled', function () {
    expect(app(Kernel::class)->getMiddlewareGroups()['web'])->toContain(BotGate::class);

    withConfig([]);

    expect(app(Kernel::class)->getMiddlewareGroups()['web'] ?? [])->not->toContain(BotGate::class);
});
