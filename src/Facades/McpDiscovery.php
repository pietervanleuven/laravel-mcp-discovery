<?php

namespace PieterVanLeuven\McpDiscovery\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \PieterVanLeuven\McpDiscovery\ServerRepository servers()
 * @method static \Illuminate\Support\Collection<string, \PieterVanLeuven\McpDiscovery\Emitters\Emitter> emitters()
 * @method static \Illuminate\Support\Collection<string, \PieterVanLeuven\McpDiscovery\Emitters\Emitter> enabledEmitters()
 * @method static \PieterVanLeuven\McpDiscovery\Emitters\Emitter|null emitter(string $key)
 * @method static bool emitterEnabled(string $key)
 * @method static string cardUrl(\PieterVanLeuven\McpDiscovery\ServerDescriptor $server)
 * @method static list<string> linkValues(string $rel = 'mcp')
 * @method static \PieterVanLeuven\McpDiscovery\Bots\Detector detector()
 * @method static bool isBot(\Illuminate\Http\Request $request)
 *
 * @see \PieterVanLeuven\McpDiscovery\McpDiscovery
 */
class McpDiscovery extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \PieterVanLeuven\McpDiscovery\McpDiscovery::class;
    }
}
