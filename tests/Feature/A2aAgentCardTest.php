<?php

it('is off by default', function () {
    $this->getJson('/.well-known/agent-card.json')->assertNotFound();
});

it('describes the primary server with its tools as skills', function () {
    withConfig([
        'mcp-discovery.emitters.a2a_agent_card.enabled' => true,
        'mcp-discovery.emitters.a2a_agent_card.url' => 'https://example.com/a2a',
    ]);

    $this->getJson('/.well-known/agent-card.json')
        ->assertOk()
        ->assertJsonPath('name', 'Blog')
        ->assertJsonPath('url', 'https://example.com/a2a')
        ->assertJsonPath('protocolVersion', '0.3.0')
        ->assertJsonPath('skills.0.id', 'search-articles')
        ->assertJsonPath('skills.1.name', 'Get article');
});
