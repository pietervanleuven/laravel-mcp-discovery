<?php

namespace PieterVanLeuven\McpDiscovery\Exceptions;

use Exception;
use PieterVanLeuven\McpDiscovery\Bots\Detector;
use PieterVanLeuven\McpDiscovery\Emitters\Emitter;

class InvalidConfiguration extends Exception
{
    public static function invalidEmitter(string $key, string $class): self
    {
        return new self("The emitter `{$key}` uses class `{$class}`, which does not extend `".Emitter::class.'`.');
    }

    public static function invalidDetector(string $class): self
    {
        return new self("The bot detector `{$class}` does not implement `".Detector::class.'`.');
    }

    public static function serverNotRegistered(string $key, string $class): self
    {
        return new self("The MCP server `{$key}` ({$class}) is not registered with Mcp::web() and has no `url` configured.");
    }
}
