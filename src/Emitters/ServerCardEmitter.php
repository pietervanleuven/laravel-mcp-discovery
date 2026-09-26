<?php

namespace PieterVanLeuven\McpDiscovery\Emitters;

use Illuminate\Routing\Router;
use PieterVanLeuven\McpDiscovery\Enums\AuthScheme;
use PieterVanLeuven\McpDiscovery\Http\Controllers\WellKnownController;
use PieterVanLeuven\McpDiscovery\ServerDescriptor;
use PieterVanLeuven\McpDiscovery\Support\Url;

/**
 * MCP Server Cards.
 *
 * @see https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2127
 */
class ServerCardEmitter extends Emitter
{
    public const SCHEMA = 'https://static.modelcontextprotocol.io/schemas/v1/server-card.schema.json';

    public function path(): string
    {
        return trim((string) $this->option('path', '.well-known/mcp-server-card'), '/');
    }

    public function indexPath(): ?string
    {
        $path = $this->option('index_path');

        return is_string($path) && $path !== '' ? trim($path, '/') : null;
    }

    public function cacheSeconds(): int
    {
        return (int) $this->option('cache', 3600);
    }

    public function routes(Router $router): void
    {
        $router->get($this->path(), [WellKnownController::class, 'serverCard'])
            ->name('mcp-discovery.server-card');

        $router->get($this->path().'/{server}', [WellKnownController::class, 'serverCard'])
            ->name('mcp-discovery.server-card.show');

        if ($indexPath = $this->indexPath()) {
            $router->get($indexPath, [WellKnownController::class, 'serverCardIndex'])
                ->name('mcp-discovery.server-card.index');
        }
    }

    public function url(ServerDescriptor $server): string
    {
        return $server->primary
            ? Url::to($this->path())
            : Url::to($this->path().'/'.$server->key);
    }

    /**
     * @return array<string, mixed>
     */
    public function card(ServerDescriptor $server): array
    {
        $remote = array_filter([
            'type' => 'streamable-http',
            'url' => $server->url,
            'supportedProtocolVersions' => $server->protocolVersions ?: null,
            'headers' => $server->auth === AuthScheme::Bearer ? [[
                'name' => 'Authorization',
                'description' => 'Bearer token, sent as "Bearer <token>".',
                'isRequired' => true,
                'isSecret' => true,
            ]] : null,
        ]);

        $card = array_filter([
            '$schema' => self::SCHEMA,
            'name' => $server->name,
            'version' => $server->version,
            'title' => $server->title,
            'description' => $server->description,
            'websiteUrl' => $server->websiteUrl,
            'repository' => $server->repository,
            'icons' => $server->icons ?: null,
            'remotes' => [$remote],
        ], fn ($value) => $value !== null);

        if ($this->option('include_capabilities', false)) {
            $card['capabilities'] = (object) $server->capabilities;
            $card['tools'] = $server->tools();
            $card['resources'] = $server->resources();
            $card['prompts'] = $server->prompts();
        }

        return $card;
    }
}
