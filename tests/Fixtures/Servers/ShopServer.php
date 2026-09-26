<?php

namespace PieterVanLeuven\McpDiscovery\Tests\Fixtures\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use PieterVanLeuven\McpDiscovery\Attributes\Discoverable;

#[Name('Shop')]
#[Version('2.1.0')]
#[Discoverable]
class ShopServer extends Server
{
    protected string $instructions = <<<'MARKDOWN'
        Use the shop tools to browse the catalogue.

        Prices are in EUR.
    MARKDOWN;
}
