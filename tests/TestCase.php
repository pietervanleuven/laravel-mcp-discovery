<?php

namespace PieterVanLeuven\McpDiscovery\Tests;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\McpServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use PieterVanLeuven\McpDiscovery\McpDiscoveryServiceProvider;
use PieterVanLeuven\McpDiscovery\Tests\Fixtures\Servers\BlogServer;
use PieterVanLeuven\McpDiscovery\Tests\Fixtures\Servers\HiddenServer;
use PieterVanLeuven\McpDiscovery\Tests\Fixtures\Servers\InternalServer;
use PieterVanLeuven\McpDiscovery\Tests\Fixtures\Servers\ShopServer;

class TestCase extends Orchestra
{
    /**
     * Config applied before the package boots. Set it with withConfig() in tests,
     * because emitter routes and middleware are registered at boot.
     *
     * @var array<string, mixed>
     */
    public static array $config = [];

    protected function tearDown(): void
    {
        static::$config = [];

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            McpServiceProvider::class,
            McpDiscoveryServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        tap($app['config'], function (Repository $config) {
            $config->set('app.url', 'https://example.com');
            $config->set('app.name', 'Example');
            $config->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
            $config->set('mcp-discovery.defaults.website_url', 'https://example.com');

            foreach (static::$config as $key => $value) {
                $config->set($key, $value);
            }
        });
    }

    protected function defineRoutes($router): void
    {
        Mcp::web('/mcp/blog', BlogServer::class);
        Mcp::web('/mcp/shop', ShopServer::class)->middleware('auth:sanctum');
        Mcp::web('/mcp/ops', InternalServer::class);
        Mcp::web('/mcp/hidden', HiddenServer::class);

        Route::middleware('web')->group(function () {
            Route::get('/', fn () => '<h1>Home</h1>');
            Route::get('/articles/hello', fn () => '<h1>Hello</h1>');
            Route::get('/api/data', fn () => response()->json(['ok' => true]));
        });
    }
}
