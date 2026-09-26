<?php

namespace PieterVanLeuven\McpDiscovery\Tests\Fixtures\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use PieterVanLeuven\McpDiscovery\Attributes\Discoverable;

#[Name('Secure')]
#[Discoverable(name: 'com.example/secure')]
class SecureServer extends Server {}
