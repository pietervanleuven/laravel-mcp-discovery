<?php

namespace PieterVanLeuven\McpDiscovery;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Mcp\Schema\Icon;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Primitive;
use Laravel\Mcp\Server\Registrar;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Laravel\SerializableClosure\SerializableClosure;
use Laravel\SerializableClosure\Serializers\Native;
use Laravel\SerializableClosure\Serializers\Signed;
use Laravel\SerializableClosure\Support\SelfReference;
use Laravel\SerializableClosure\UnsignedSerializableClosure;
use PieterVanLeuven\McpDiscovery\Attributes\Discoverable;
use PieterVanLeuven\McpDiscovery\Enums\AuthScheme;
use PieterVanLeuven\McpDiscovery\Exceptions\InvalidConfiguration;
use PieterVanLeuven\McpDiscovery\Support\Url;
use ReflectionClass;
use ReflectionFunction;
use Throwable;

/**
 * Resolves the servers registered with laravel/mcp into ServerDescriptors.
 *
 * Metadata sources, later ones win: laravel/mcp attributes, #[Discoverable], config.
 */
class ServerRepository
{
    /** @var Collection<string, ServerDescriptor>|null */
    protected ?Collection $servers = null;

    /** @var array<class-string<Server>, Route>|null */
    protected ?array $webRoutes = null;

    public function __construct(
        protected Container $container,
        protected Config $config,
        protected Router $router,
    ) {}

    /**
     * @return Collection<string, ServerDescriptor>
     */
    public function all(): Collection
    {
        return $this->servers ??= $this->resolve();
    }

    public function find(string $key): ?ServerDescriptor
    {
        return $this->all()->get($key);
    }

    public function primary(): ?ServerDescriptor
    {
        return $this->all()->first(fn (ServerDescriptor $server) => $server->primary)
            ?? $this->all()->first();
    }

    /**
     * Servers visible to the current viewer.
     *
     * @return Collection<string, ServerDescriptor>
     */
    public function visible(bool $includePrivate = false): Collection
    {
        return $this->all()->filter(fn (ServerDescriptor $server) => $includePrivate || $server->public);
    }

    public function flush(): void
    {
        $this->servers = null;
        $this->webRoutes = null;
    }

    /**
     * Every server class registered with Mcp::web(), keyed by class.
     *
     * @return array<class-string<Server>, Route>
     */
    public function webRoutes(): array
    {
        if ($this->webRoutes !== null) {
            return $this->webRoutes;
        }

        $routes = [];

        foreach ($this->candidateRoutes() as $route) {
            $class = $this->serverClassFor($route);

            if ($class !== null && ! isset($routes[$class])) {
                $routes[$class] = $route;
            }
        }

        return $this->webRoutes = $routes;
    }

    /**
     * @return Collection<string, ServerDescriptor>
     */
    protected function resolve(): Collection
    {
        $configured = $this->config->get('mcp-discovery.servers', 'auto');

        $entries = $configured === 'auto'
            ? $this->autoEntries()
            : $this->explicitEntries((array) $configured);

        $servers = collect($entries)
            ->map(fn (array $entry, string $key) => $this->describe($key, $entry))
            ->keyBy(fn (ServerDescriptor $server) => $server->key);

        if ($servers->isNotEmpty() && ! $servers->contains(fn (ServerDescriptor $server) => $server->primary)) {
            $first = $servers->first();
            $servers->put($first->key, $first->asPrimary());
        }

        return $servers;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function autoEntries(): array
    {
        $entries = [];

        foreach ($this->webRoutes() as $class => $route) {
            $attribute = $this->discoverable($class);

            if ($attribute === null) {
                continue;
            }

            $entries[$attribute->key ?? $this->defaultKey($class)] = ['class' => $class];
        }

        return $entries;
    }

    /**
     * @param  array<array-key, mixed>  $configured
     * @return array<string, array<string, mixed>>
     */
    protected function explicitEntries(array $configured): array
    {
        $entries = [];

        foreach ($configured as $key => $entry) {
            $entry = is_string($entry) ? ['class' => $entry] : (array) $entry;

            /** @var class-string<Server> $class */
            $class = $entry['class'];

            $key = is_string($key) ? $key : ($this->discoverable($class)->key ?? $this->defaultKey($class));

            $entries[$key] = $entry;
        }

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    protected function describe(string $key, array $entry): ServerDescriptor
    {
        /** @var class-string<Server> $class */
        $class = $entry['class'];
        $route = $this->webRoutes()[$class] ?? null;
        $attribute = $this->discoverable($class);
        $defaults = (array) $this->config->get('mcp-discovery.defaults', []);

        $url = $entry['url'] ?? ($route !== null ? $this->urlFor($route) : null);

        if ($url === null) {
            throw InvalidConfiguration::serverNotRegistered($key, $class);
        }

        $context = $this->contextFor($class);
        $reflection = new ReflectionClass($class);

        $title = $entry['title']
            ?? $attribute->title
            ?? $this->serverAttribute($reflection, Title::class)
            ?? $context?->implementation->name;

        $description = $entry['description']
            ?? $attribute->description
            ?? $this->serverAttribute($reflection, Description::class)
            ?? $this->summarise($context->instructions);

        $icons = $context !== null
            ? array_map(fn (Icon $icon) => $icon->toArray(), $context->implementation->icons)
            : [];

        return new ServerDescriptor(
            key: $key,
            class: $class,
            name: $entry['name'] ?? $attribute->name ?? $this->defaultName($key),
            version: $entry['version'] ?? $context->implementation->version ?? '0.0.1',
            url: $url,
            title: $title,
            description: $description,
            instructions: $context !== null ? trim($context->instructions) : null,
            websiteUrl: $entry['website_url'] ?? $attribute->websiteUrl ?? $defaults['website_url'] ?? null,
            repository: $entry['repository'] ?? $attribute->repository ?? $defaults['repository'] ?? null,
            icons: $entry['icons'] ?? ($icons !== [] ? $icons : ($defaults['icons'] ?? [])),
            protocolVersions: array_values($entry['protocol_versions']
                ?? $defaults['protocol_versions']
                ?? $context->supportedProtocolVersions
                ?? []),
            auth: isset($entry['auth'])
                ? AuthScheme::from($entry['auth'])
                : ($route !== null ? $this->authFor($route) : AuthScheme::None),
            primary: (bool) ($entry['primary'] ?? $attribute->primary ?? false),
            public: (bool) ($entry['public'] ?? $attribute->public ?? true),
            capabilities: $context !== null ? $context->serverCapabilities : [],
            primitives: $context === null ? null : fn () => [
                'tools' => $this->primitives(fn () => $context->tools()),
                'resources' => $this->primitives(fn () => $context->resources()),
                'prompts' => $this->primitives(fn () => $context->prompts()),
            ],
        );
    }

    /**
     * @return iterable<Route>
     */
    protected function candidateRoutes(): iterable
    {
        if ($this->container->bound(Registrar::class)) {
            $registered = array_filter(
                $this->container->make(Registrar::class)->servers(),
                fn ($server) => $server instanceof Route,
            );

            if ($registered !== []) {
                return $registered;
            }
        }

        // With cached routes laravel/mcp never loads routes/ai.php, so the
        // registrar is empty. Fall back to the router itself.
        return array_filter(
            $this->router->getRoutes()->getRoutes(),
            fn (Route $route) => in_array('POST', $route->methods(), true),
        );
    }

    /**
     * Mcp::web() registers a closure that captures `$serverClass`.
     *
     * @return class-string<Server>|null
     */
    protected function serverClassFor(Route $route): ?string
    {
        $uses = $route->getAction('uses');

        if (is_string($uses) && str_starts_with($uses, 'O:') && str_contains($uses, 'SerializableClosure')) {
            try {
                $uses = unserialize($uses, ['allowed_classes' => [
                    SerializableClosure::class,
                    UnsignedSerializableClosure::class,
                    Native::class,
                    Signed::class,
                    SelfReference::class,
                ]])->getClosure();
            } catch (Throwable) {
                return null;
            }
        }

        if (! $uses instanceof Closure) {
            return null;
        }

        $class = (new ReflectionFunction($uses))->getStaticVariables()['serverClass'] ?? null;

        return is_string($class) && is_subclass_of($class, Server::class) ? $class : null;
    }

    /**
     * @param  class-string  $class
     */
    protected function discoverable(string $class): ?Discoverable
    {
        $attribute = (new ReflectionClass($class))->getAttributes(Discoverable::class)[0] ?? null;

        return $attribute?->newInstance();
    }

    /**
     * @param  class-string<Server>  $class
     */
    protected function contextFor(string $class): ?ServerContext
    {
        try {
            /** @var Server $server */
            $server = $this->container->make($class, ['transport' => new FakeTransporter]);

            return $server->createContext();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  ReflectionClass<Server>  $reflection
     * @param  class-string<Title|Description>  $attributeClass
     */
    protected function serverAttribute(ReflectionClass $reflection, string $attributeClass): ?string
    {
        do {
            $attribute = $reflection->getAttributes($attributeClass)[0] ?? null;

            if ($attribute !== null) {
                return $attribute->newInstance()->value;
            }
        } while ($reflection = $reflection->getParentClass());

        return null;
    }

    /**
     * @template T of Primitive
     *
     * @param  Closure(): Collection<int, T>  $resolver
     * @return list<array<string, string>>
     */
    protected function primitives(Closure $resolver): array
    {
        try {
            return $resolver()
                ->map(fn (Primitive $primitive) => array_filter([
                    'name' => $primitive->name(),
                    'title' => $primitive->title(),
                    'description' => $primitive->description(),
                ]))
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    protected function urlFor(Route $route): string
    {
        $path = '/'.ltrim($route->uri(), '/');

        if ($domain = $route->getDomain()) {
            $scheme = parse_url((string) $this->config->get('app.url'), PHP_URL_SCHEME) ?: 'https';

            return "{$scheme}://{$domain}".($path === '/' ? '' : $path);
        }

        return Url::to($path);
    }

    protected function authFor(Route $route): AuthScheme
    {
        $guards = collect($route->gatherMiddleware())
            ->filter(fn ($middleware) => is_string($middleware) && ($middleware === 'auth' || str_starts_with($middleware, 'auth:')))
            ->flatMap(fn (string $middleware) => explode(',', Str::after($middleware, 'auth:')));

        if ($guards->isEmpty()) {
            return AuthScheme::None;
        }

        $hasOAuthMetadata = collect($this->router->getRoutes()->getRoutes())
            ->contains(fn (Route $candidate) => $candidate->uri() === '.well-known/oauth-protected-resource');

        if ($hasOAuthMetadata && ! $guards->contains('sanctum')) {
            return AuthScheme::OAuth;
        }

        return AuthScheme::Bearer;
    }

    /**
     * @param  class-string  $class
     */
    protected function defaultKey(string $class): string
    {
        $base = class_basename($class);

        return Str::kebab(Str::endsWith($base, 'Server') && $base !== 'Server' ? Str::beforeLast($base, 'Server') : $base);
    }

    /**
     * Reverse-DNS name built from APP_URL: https://blog.example.com -> com.example.blog/{key}.
     */
    protected function defaultName(string $key): string
    {
        $host = parse_url((string) $this->config->get('app.url'), PHP_URL_HOST) ?: 'localhost';

        return implode('.', array_reverse(explode('.', $host))).'/'.$key;
    }

    protected function summarise(?string $instructions): ?string
    {
        if ($instructions === null || trim($instructions) === '') {
            return null;
        }

        $paragraph = trim(preg_split('/\R\s*\R/', trim($instructions))[0]);

        return Str::limit((string) preg_replace('/\s+/', ' ', $paragraph), 97);
    }
}
