<?php

namespace PieterVanLeuven\McpDiscovery\Console;

use Illuminate\Console\Command;
use PieterVanLeuven\McpDiscovery\Emitters\TextFileEmitter;
use PieterVanLeuven\McpDiscovery\McpDiscovery;
use PieterVanLeuven\McpDiscovery\ServerDescriptor;
use Throwable;

class ValidateCommand extends Command
{
    public const NAME_PATTERN = '/^[a-zA-Z0-9.-]+\/[a-zA-Z0-9._-]+$/';

    protected $signature = 'mcp-discovery:validate';

    protected $description = 'Check server metadata and discovery configuration for problems';

    /** @var list<string> */
    protected array $errors = [];

    /** @var list<string> */
    protected array $warnings = [];

    public function handle(McpDiscovery $discovery): int
    {
        try {
            $servers = $discovery->servers()->all();
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($servers->isEmpty()) {
            $this->warnings[] = 'No discoverable MCP servers found.';
        }

        foreach ($servers as $server) {
            $this->validateServer($server);
        }

        foreach ($servers->groupBy('name')->filter(fn ($group) => $group->count() > 1)->keys() as $name) {
            $this->errors[] = "More than one server uses the name `{$name}`.";
        }

        if ($servers->filter(fn (ServerDescriptor $server) => $server->primary)->count() > 1) {
            $this->errors[] = 'More than one server is marked as primary.';
        }

        $this->validateEmitters($discovery);

        foreach ($this->warnings as $warning) {
            $this->components->warn($warning);
        }

        foreach ($this->errors as $error) {
            $this->components->error($error);
        }

        if ($this->errors === []) {
            $this->components->info(sprintf('%d server(s) valid, %d warning(s).', $servers->count(), count($this->warnings)));

            return self::SUCCESS;
        }

        return self::FAILURE;
    }

    protected function validateServer(ServerDescriptor $server): void
    {
        $prefix = "[{$server->key}]";

        if (! preg_match(self::NAME_PATTERN, $server->name)) {
            $this->errors[] = "{$prefix} Name `{$server->name}` must be reverse-DNS with a slash, e.g. `com.example/blog`.";
        }

        if ($server->version === '' || strtolower($server->version) === 'latest') {
            $this->errors[] = "{$prefix} Version must be a specific version, not `{$server->version}`.";
        } elseif (! preg_match('/^\d+\.\d+\.\d+([-+].+)?$/', $server->version)) {
            $this->warnings[] = "{$prefix} Version `{$server->version}` is not semantic versioning.";
        }

        if ($server->description === null || $server->description === '') {
            $this->warnings[] = "{$prefix} No description. Add #[Description] or #[Discoverable(description: ...)].";
        } elseif (mb_strlen($server->description) > 100) {
            $this->warnings[] = "{$prefix} Description is longer than 100 characters; the MCP Registry will reject it.";
        }

        if (! filter_var($server->url, FILTER_VALIDATE_URL)) {
            $this->errors[] = "{$prefix} URL `{$server->url}` is not a valid absolute URL. Check APP_URL.";
        } elseif (parse_url($server->url, PHP_URL_SCHEME) !== 'https') {
            $message = "{$prefix} URL `{$server->url}` is not HTTPS.";
            app()->isProduction() ? $this->errors[] = $message : $this->warnings[] = $message;
        }

        if ($server->protocolVersions === []) {
            $this->warnings[] = "{$prefix} No supported protocol versions.";
        }
    }

    protected function validateEmitters(McpDiscovery $discovery): void
    {
        foreach ($discovery->enabledEmitters() as $emitter) {
            if (! $emitter instanceof TextFileEmitter) {
                continue;
            }

            $file = public_path($emitter->fileName());

            if ($emitter->mode() === 'route' && is_file($file)) {
                $this->warnings[] = "[{$emitter->key()}] public/{$emitter->fileName()} exists, so the web server serves it and the dynamic route never runs. Set mode to 'file' and run `php artisan mcp-discovery:write`.";
            }

            if ($emitter->mode() === 'file' && (! is_file($file) || (string) file_get_contents($file) !== $emitter->inject((string) file_get_contents($file)))) {
                $this->warnings[] = "[{$emitter->key()}] public/{$emitter->fileName()} is out of date. Run `php artisan mcp-discovery:write`.";
            }
        }

        if (config('mcp-discovery.bot_gate.enabled') && $discovery->servers()->visible()->isEmpty()) {
            $this->warnings[] = '[bot_gate] Enabled, but there are no public servers to point bots at, so it does nothing.';
        }

        if ($discovery->emitterEnabled('a2a_agent_card') && $discovery->emitterOption('a2a_agent_card', 'url') === null) {
            $this->warnings[] = '[a2a_agent_card] No `url` set; the card falls back to the MCP endpoint, which does not speak A2A.';
        }
    }
}
