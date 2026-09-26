<?php

use PieterVanLeuven\McpDiscovery\Emitters\LlmsTxtEmitter;
use PieterVanLeuven\McpDiscovery\McpDiscovery;

it('serves llms.txt with an MCP section', function () {
    $response = $this->get('/llms.txt')->assertOk();

    expect($response->headers->get('Content-Type'))->toStartWith('text/plain');

    expect($response->getContent())
        ->toStartWith("# Example\n")
        ->toContain('## MCP servers')
        ->toContain('- [Blog](https://example.com/mcp/blog): Search and read articles from example.com. Streamable HTTP endpoint. Server card: https://example.com/.well-known/mcp-server-card')
        ->toContain('- [Shop](https://example.com/mcp/shop)')
        ->not->toContain('mcp/ops');
});

it('appends to a base file', function () {
    $base = tempnam(sys_get_temp_dir(), 'llms');
    file_put_contents($base, "# My site\n\n> All about me.\n\n## Docs\n\n- [About](https://example.com/about)\n");

    withConfig(['mcp-discovery.emitters.llms_txt.base' => $base]);

    expect($this->get('/llms.txt')->getContent())
        ->toStartWith("# My site\n")
        ->toContain("- [About](https://example.com/about)\n\n<!-- mcp-discovery:start -->\n## MCP servers");

    unlink($base);
});

it('replaces an existing section instead of duplicating it', function () {
    $emitter = app(McpDiscovery::class)->emitter('llms_txt');
    assert($emitter instanceof LlmsTxtEmitter);

    $once = $emitter->inject("# Site\n");
    $twice = $emitter->inject($once);

    expect($twice)->toBe($once)
        ->and(substr_count($twice, '## MCP servers'))->toBe(1);
});

it('does not register a route in file mode', function () {
    withConfig(['mcp-discovery.emitters.llms_txt.mode' => 'file']);

    $this->get('/llms.txt')->assertNotFound();
});
