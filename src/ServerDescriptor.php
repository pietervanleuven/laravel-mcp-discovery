<?php

namespace PieterVanLeuven\McpDiscovery;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Laravel\Mcp\Server;
use PieterVanLeuven\McpDiscovery\Enums\AuthScheme;

/**
 * Everything the emitters need to know about one MCP server.
 *
 * Tools, resources and prompts are resolved lazily: building them
 * instantiates every primitive, which most requests never need.
 *
 * @implements Arrayable<string, mixed>
 */
final class ServerDescriptor implements Arrayable
{
    /** @var array{tools: list<array<string, string>>, resources: list<array<string, string>>, prompts: list<array<string, string>>}|null */
    private ?array $resolvedPrimitives = null;

    /**
     * @param  class-string<Server>  $class
     * @param  list<string>  $protocolVersions
     * @param  list<array<string, mixed>>  $icons
     * @param  array<string, mixed>|null  $repository
     * @param  array<string, mixed>  $capabilities
     * @param  (Closure(): array{tools: list<array<string, string>>, resources: list<array<string, string>>, prompts: list<array<string, string>>})|null  $primitives
     */
    public function __construct(
        public readonly string $key,
        public readonly string $class,
        public readonly string $name,
        public readonly string $version,
        public readonly string $url,
        public readonly ?string $title = null,
        public readonly ?string $description = null,
        public readonly ?string $instructions = null,
        public readonly ?string $websiteUrl = null,
        public readonly ?array $repository = null,
        public readonly array $icons = [],
        public readonly array $protocolVersions = [],
        public readonly AuthScheme $auth = AuthScheme::None,
        public readonly bool $primary = false,
        public readonly bool $public = true,
        public readonly array $capabilities = [],
        private readonly ?Closure $primitives = null,
    ) {}

    /**
     * @return list<array<string, string>> name, title and description of each tool
     */
    public function tools(): array
    {
        return $this->primitives()['tools'];
    }

    /**
     * @return list<array<string, string>>
     */
    public function resources(): array
    {
        return $this->primitives()['resources'];
    }

    /**
     * @return list<array<string, string>>
     */
    public function prompts(): array
    {
        return $this->primitives()['prompts'];
    }

    public function asPrimary(): self
    {
        return new self(
            key: $this->key,
            class: $this->class,
            name: $this->name,
            version: $this->version,
            url: $this->url,
            title: $this->title,
            description: $this->description,
            instructions: $this->instructions,
            websiteUrl: $this->websiteUrl,
            repository: $this->repository,
            icons: $this->icons,
            protocolVersions: $this->protocolVersions,
            auth: $this->auth,
            primary: true,
            public: $this->public,
            capabilities: $this->capabilities,
            primitives: $this->primitives,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'class' => $this->class,
            'name' => $this->name,
            'version' => $this->version,
            'url' => $this->url,
            'title' => $this->title,
            'description' => $this->description,
            'instructions' => $this->instructions,
            'website_url' => $this->websiteUrl,
            'repository' => $this->repository,
            'icons' => $this->icons,
            'protocol_versions' => $this->protocolVersions,
            'auth' => $this->auth->value,
            'primary' => $this->primary,
            'public' => $this->public,
            'capabilities' => $this->capabilities,
            'tools' => $this->tools(),
            'resources' => $this->resources(),
            'prompts' => $this->prompts(),
        ];
    }

    /**
     * @return array{tools: list<array<string, string>>, resources: list<array<string, string>>, prompts: list<array<string, string>>}
     */
    private function primitives(): array
    {
        return $this->resolvedPrimitives ??= $this->primitives !== null
            ? ($this->primitives)()
            : ['tools' => [], 'resources' => [], 'prompts' => []];
    }
}
