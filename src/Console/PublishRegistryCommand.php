<?php

namespace PieterVanLeuven\McpDiscovery\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use PieterVanLeuven\McpDiscovery\Enums\AuthScheme;
use PieterVanLeuven\McpDiscovery\McpDiscovery;
use PieterVanLeuven\McpDiscovery\ServerDescriptor;

/**
 * Writes a server.json for the official MCP Registry. Publishing itself needs
 * namespace verification (DNS, HTTP or GitHub), which `mcp-publisher` handles.
 *
 * @see https://registry.modelcontextprotocol.io/
 */
class PublishRegistryCommand extends Command
{
    public const SCHEMA = 'https://static.modelcontextprotocol.io/schemas/2025-12-11/server.schema.json';

    protected $signature = 'mcp-discovery:publish-registry
        {server? : Server key (defaults to the primary server)}
        {--output=server.json : Path, relative to the project root}';

    protected $description = 'Generate a server.json for the MCP Registry';

    public function handle(McpDiscovery $discovery, Filesystem $files): int
    {
        $key = $this->argument('server');
        $server = is_string($key) ? $discovery->servers()->find($key) : $discovery->servers()->primary();

        if ($server === null) {
            $this->components->error(is_string($key) ? "No discoverable server with key `{$key}`." : 'No discoverable servers.');

            return self::FAILURE;
        }

        $path = base_path((string) $this->option('output'));
        $files->put($path, json_encode($this->serverJson($server), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        $this->components->info("Wrote {$path}.");
        $this->line('  Publish it with the official CLI (https://github.com/modelcontextprotocol/registry):');
        $this->line('    mcp-publisher login dns --domain '.$this->domain($server).' --private-key <key>');
        $this->line('    mcp-publisher publish '.$this->option('output'));

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    public function serverJson(ServerDescriptor $server): array
    {
        return array_filter([
            '$schema' => self::SCHEMA,
            'name' => $server->name,
            'title' => $server->title,
            'description' => $server->description ?? $server->title ?? $server->name,
            'version' => $server->version,
            'websiteUrl' => $server->websiteUrl,
            'repository' => $server->repository,
            'icons' => $server->icons ?: null,
            'remotes' => [array_filter([
                'type' => 'streamable-http',
                'url' => $server->url,
                'headers' => $server->auth === AuthScheme::Bearer ? [[
                    'name' => 'Authorization',
                    'description' => 'Bearer token, sent as "Bearer <token>".',
                    'isRequired' => true,
                    'isSecret' => true,
                ]] : null,
            ])],
        ], fn ($value) => $value !== null);
    }

    protected function domain(ServerDescriptor $server): string
    {
        $namespace = explode('/', $server->name)[0];

        return implode('.', array_reverse(explode('.', $namespace)));
    }
}
