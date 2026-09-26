<?php

namespace PieterVanLeuven\McpDiscovery\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Discoverable
{
    /**
     * @param  string|null  $key  URL segment for /.well-known/mcp-server-card/{key}. Defaults to the class name without "Server", kebab-cased.
     * @param  string|null  $name  Reverse-DNS registry name, e.g. "com.example/blog".
     * @param  array{url: string, source?: string, subfolder?: string}|null  $repository
     */
    public function __construct(
        public ?string $key = null,
        public ?string $name = null,
        public ?string $title = null,
        public ?string $description = null,
        public bool $primary = false,
        public bool $public = true,
        public ?string $websiteUrl = null,
        public ?array $repository = null,
    ) {}
}
