<?php

namespace PieterVanLeuven\McpDiscovery;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PieterVanLeuven\McpDiscovery\Bots\Detector;
use PieterVanLeuven\McpDiscovery\Emitters\Contracts\ProvidesLinks;
use PieterVanLeuven\McpDiscovery\Emitters\Emitter;
use PieterVanLeuven\McpDiscovery\Emitters\ServerCardEmitter;
use PieterVanLeuven\McpDiscovery\Exceptions\InvalidConfiguration;
use PieterVanLeuven\McpDiscovery\Support\Url;
use Symfony\Component\HttpFoundation\Response;

class McpDiscovery
{
    /** @var Collection<string, Emitter>|null */
    protected ?Collection $emitters = null;

    public function __construct(
        protected Container $container,
        protected Config $config,
        protected ServerRepository $servers,
    ) {}

    public function servers(): ServerRepository
    {
        return $this->servers;
    }

    /**
     * Every configured emitter, enabled or not.
     *
     * @return Collection<string, Emitter>
     */
    public function emitters(): Collection
    {
        return $this->emitters ??= collect((array) $this->config->get('mcp-discovery.emitters', []))
            ->filter(fn ($config) => is_array($config) && isset($config['class']))
            ->map(function (array $config, string $key): Emitter {
                $emitter = $this->container->make($config['class'], [
                    'discovery' => $this,
                    'key' => $key,
                    'config' => $config,
                ]);

                if (! $emitter instanceof Emitter) {
                    throw InvalidConfiguration::invalidEmitter($key, $config['class']);
                }

                return $emitter;
            });
    }

    /**
     * @return Collection<string, Emitter>
     */
    public function enabledEmitters(): Collection
    {
        return $this->emitters()->filter(fn (Emitter $emitter) => $emitter->enabled());
    }

    public function emitter(string $key): ?Emitter
    {
        return $this->emitters()->get($key);
    }

    public function emitterEnabled(string $key): bool
    {
        return (bool) $this->emitter($key)?->enabled();
    }

    public function emitterOption(string $key, string $option, mixed $default = null): mixed
    {
        return $this->emitter($key)?->option($option, $default) ?? $default;
    }

    public function cardUrl(ServerDescriptor $server): string
    {
        $emitter = $this->emitter('server_card');

        return $emitter instanceof ServerCardEmitter
            ? $emitter->url($server)
            : Url::to('.well-known/mcp-server-card/'.$server->key);
    }

    /**
     * Link header values pointing at every public server, plus the primary server card.
     *
     * @return list<string>
     */
    public function linkValues(string $rel = 'mcp'): array
    {
        $links = $this->servers->visible()
            ->map(fn (ServerDescriptor $server) => sprintf('<%s>; rel="%s"; title="%s"', $server->url, $rel, addcslashes($server->title ?? $server->name, '"\\')))
            ->values()
            ->all();

        $primary = $this->servers->primary();

        if ($primary !== null && $primary->public && $this->emitterEnabled('server_card')) {
            $links[] = sprintf('<%s>; rel="service-desc"; type="application/json"', $this->cardUrl($primary));
        }

        return $links;
    }

    /**
     * Link header values every enabled emitter wants on this response.
     *
     * @return list<string>
     */
    public function links(Request $request, Response $response): array
    {
        return $this->enabledEmitters()
            ->filter(fn (Emitter $emitter) => $emitter instanceof ProvidesLinks)
            ->flatMap(fn (ProvidesLinks $emitter) => $emitter->links($request, $response))
            ->unique()
            ->values()
            ->all();
    }

    public function detector(): Detector
    {
        $class = $this->config->get('mcp-discovery.bot_gate.detector');
        $detector = $this->container->make($class);

        if (! $detector instanceof Detector) {
            throw InvalidConfiguration::invalidDetector((string) $class);
        }

        return $detector;
    }

    public function isBot(Request $request): bool
    {
        return $this->detector()->isBot($request);
    }
}
