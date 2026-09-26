<?php

namespace PieterVanLeuven\McpDiscovery\Tests\Fixtures\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;

/**
 * Registered with Mcp::web() but not marked #[Discoverable].
 */
#[Name('Hidden')]
class HiddenServer extends Server {}
