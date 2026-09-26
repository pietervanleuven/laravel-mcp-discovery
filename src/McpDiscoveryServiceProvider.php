<?php

namespace PieterVanLeuven\McpDiscovery;

use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Routing\Router;
use PieterVanLeuven\McpDiscovery\Bots\WebBotAuthVerifier;
use PieterVanLeuven\McpDiscovery\Console\ListCommand;
use PieterVanLeuven\McpDiscovery\Console\PublishRegistryCommand;
use PieterVanLeuven\McpDiscovery\Console\ValidateCommand;
use PieterVanLeuven\McpDiscovery\Console\WriteCommand;
use PieterVanLeuven\McpDiscovery\Emitters\Contracts\ProvidesLinks;
use PieterVanLeuven\McpDiscovery\Http\Middleware\AddDiscoveryHeaders;
use PieterVanLeuven\McpDiscovery\Http\Middleware\BotGate;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class McpDiscoveryServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-mcp-discovery')
            ->hasConfigFile('mcp-discovery')
            ->hasCommands([
                ListCommand::class,
                ValidateCommand::class,
                WriteCommand::class,
                PublishRegistryCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        // Scoped, not singleton: descriptors may depend on the request (tool
        // shouldRegister(), private servers), which matters under Octane.
        $this->app->scoped(ServerRepository::class);
        $this->app->scoped(McpDiscovery::class);
        $this->app->scoped(WebBotAuthVerifier::class);
    }

    public function packageBooted(): void
    {
        $this->registerRoutes();
        $this->registerMiddleware();

        // Resolve fresh per request, so emitters read the final config.
        $this->app->forgetInstance(McpDiscovery::class);
        $this->app->forgetInstance(ServerRepository::class);
    }

    protected function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $router = $this->app->make(Router::class);

        $this->app->make(McpDiscovery::class)
            ->enabledEmitters()
            ->each(fn ($emitter) => $emitter->routes($router));
    }

    protected function registerMiddleware(): void
    {
        if (! config('mcp-discovery.register_middleware', true)) {
            return;
        }

        $middleware = [];

        if (config('mcp-discovery.bot_gate.enabled', false)) {
            $middleware[] = BotGate::class;
        }

        $providesLinks = $this->app->make(McpDiscovery::class)
            ->enabledEmitters()
            ->contains(fn ($emitter) => $emitter instanceof ProvidesLinks);

        if ($providesLinks) {
            $middleware[] = AddDiscoveryHeaders::class;
        }

        if ($middleware === []) {
            return;
        }

        // Go through the kernel: it re-syncs its groups onto the router, which
        // would drop anything pushed onto the router directly.
        $this->callAfterResolving(HttpKernelContract::class, function (mixed $kernel) use ($middleware): void {
            if (! $kernel instanceof HttpKernel || ! array_key_exists('web', $kernel->getMiddlewareGroups())) {
                return;
            }

            foreach ($middleware as $class) {
                $kernel->appendMiddlewareToGroup('web', $class);
            }
        });
    }
}
