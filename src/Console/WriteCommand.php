<?php

namespace PieterVanLeuven\McpDiscovery\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use PieterVanLeuven\McpDiscovery\Emitters\TextFileEmitter;
use PieterVanLeuven\McpDiscovery\McpDiscovery;

class WriteCommand extends Command
{
    protected $signature = 'mcp-discovery:write {--dry-run : Print the result instead of writing it}';

    protected $description = "Write the MCP sections into public/llms.txt and public/robots.txt (emitters in 'file' mode)";

    public function handle(McpDiscovery $discovery, Filesystem $files): int
    {
        $emitters = $discovery->enabledEmitters()
            ->filter(fn ($emitter) => $emitter instanceof TextFileEmitter && $emitter->mode() === 'file');

        if ($emitters->isEmpty()) {
            $this->components->info("No enabled emitters use 'file' mode. Nothing to write.");

            return self::SUCCESS;
        }

        /** @var TextFileEmitter $emitter */
        foreach ($emitters as $emitter) {
            $path = public_path($emitter->fileName());
            $current = $files->exists($path) ? $files->get($path) : '';
            $updated = $emitter->inject($current);

            if ($this->option('dry-run')) {
                $this->components->twoColumnDetail("public/{$emitter->fileName()}", 'dry run');
                $this->line($updated);

                continue;
            }

            if ($updated === $current) {
                $this->components->twoColumnDetail("public/{$emitter->fileName()}", '<fg=gray>unchanged</>');

                continue;
            }

            $files->put($path, $updated);
            $this->components->twoColumnDetail("public/{$emitter->fileName()}", '<fg=green>written</>');
        }

        return self::SUCCESS;
    }
}
