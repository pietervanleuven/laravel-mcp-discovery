<?php

namespace PieterVanLeuven\McpDiscovery\Emitters;

use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use PieterVanLeuven\McpDiscovery\McpDiscovery;

/**
 * One discovery standard. Emitters only read ServerDescriptors, so a spec
 * change stays inside a single class.
 */
abstract class Emitter
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected McpDiscovery $discovery,
        protected string $key,
        protected array $config = [],
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false);
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->config, $key, $default);
    }

    /**
     * Register the HTTP routes this emitter serves. Called once at boot, only when enabled.
     */
    public function routes(Router $router): void
    {
        //
    }
}
