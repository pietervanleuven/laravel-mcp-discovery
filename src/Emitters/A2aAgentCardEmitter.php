<?php

namespace PieterVanLeuven\McpDiscovery\Emitters;

use Illuminate\Routing\Router;
use PieterVanLeuven\McpDiscovery\Http\Controllers\WellKnownController;

/**
 * A2A Agent Card built from the primary server's tools.
 *
 * @see https://a2a-protocol.org/latest/specification/#5-agent-discovery-the-agent-card
 */
class A2aAgentCardEmitter extends Emitter
{
    public function routes(Router $router): void
    {
        $router->get(trim((string) $this->option('path', '.well-known/agent-card.json'), '/'), [WellKnownController::class, 'agentCard'])
            ->name('mcp-discovery.agent-card');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function card(): ?array
    {
        $server = $this->discovery->servers()->primary();

        if ($server === null || ! $server->public) {
            return null;
        }

        return array_filter([
            'protocolVersion' => (string) $this->option('protocol_version', '0.3.0'),
            'name' => $server->title ?? $server->name,
            'description' => $server->description ?? $server->instructions ?? '',
            'url' => $this->option('url') ?? $server->url,
            'version' => $server->version,
            'iconUrl' => $server->icons[0]['src'] ?? null,
            'documentationUrl' => $server->websiteUrl,
            'provider' => $server->websiteUrl !== null ? [
                'organization' => (string) config('app.name'),
                'url' => $server->websiteUrl,
            ] : null,
            'capabilities' => (object) [],
            'defaultInputModes' => ['text/plain', 'application/json'],
            'defaultOutputModes' => ['text/plain', 'application/json'],
            'skills' => array_map(fn (array $tool) => [
                'id' => $tool['name'],
                'name' => $tool['title'] ?? $tool['name'],
                'description' => $tool['description'] ?? $tool['name'],
                'tags' => ['mcp'],
            ], $server->tools()),
        ], fn ($value) => $value !== null);
    }
}
