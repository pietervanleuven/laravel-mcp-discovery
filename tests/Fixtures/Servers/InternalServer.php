<?php

namespace PieterVanLeuven\McpDiscovery\Tests\Fixtures\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use PieterVanLeuven\McpDiscovery\Attributes\Discoverable;

#[Name('Internal')]
#[Discoverable(key: 'ops', name: 'com.example/ops', description: 'Operations tooling.', public: false)]
class InternalServer extends Server {}
