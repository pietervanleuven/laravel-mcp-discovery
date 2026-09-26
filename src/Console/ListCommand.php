<?php

namespace PieterVanLeuven\McpDiscovery\Console;

use Illuminate\Console\Command;
use PieterVanLeuven\McpDiscovery\Emitters\Emitter;
use PieterVanLeuven\McpDiscovery\McpDiscovery;
use PieterVanLeuven\McpDiscovery\ServerDescriptor;

class ListCommand extends Command
{
    protected $signature = 'mcp-discovery:list {--json : Output the server descriptors as JSON}';

    protected $description = 'List the MCP servers and discovery emitters this app publishes';

    public function handle(McpDiscovery $discovery): int
    {
        $servers = $discovery->servers()->all();

        if ($this->option('json')) {
            $this->line((string) json_encode($servers->map->toArray()->values(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($servers->isEmpty()) {
            $this->components->warn('No discoverable MCP servers. Add #[Discoverable] to a server registered with Mcp::web(), or configure `mcp-discovery.servers`.');
        } else {
            $this->table(
                ['Key', 'Name', 'Version', 'URL', 'Auth', 'Primary', 'Public', 'Tools'],
                $servers->map(fn (ServerDescriptor $server) => [
                    $server->key,
                    $server->name,
                    $server->version,
                    $server->url,
                    $server->auth->value,
                    $server->primary ? 'yes' : '',
                    $server->public ? 'yes' : 'no',
                    count($server->tools()),
                ])->values()->all(),
            );
        }

        $this->newLine();

        $this->table(
            ['Emitter', 'Enabled', 'Class'],
            $discovery->emitters()->map(fn (Emitter $emitter) => [
                $emitter->key(),
                $emitter->enabled() ? 'yes' : 'no',
                $emitter::class,
            ])->values()->all(),
        );

        return self::SUCCESS;
    }
}
