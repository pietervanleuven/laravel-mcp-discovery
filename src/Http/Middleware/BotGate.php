<?php

namespace PieterVanLeuven\McpDiscovery\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Illuminate\Support\Arr;
use PieterVanLeuven\McpDiscovery\Bots\WebBotAuthVerifier;
use PieterVanLeuven\McpDiscovery\Enums\SignatureStatus;
use PieterVanLeuven\McpDiscovery\McpDiscovery;
use PieterVanLeuven\McpDiscovery\ServerDescriptor;
use PieterVanLeuven\McpDiscovery\Support\LinkHeader;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirect-instead-of-reject: bots get a short response pointing at the MCP
 * server instead of the HTML page.
 */
class BotGate
{
    public function __construct(
        protected McpDiscovery $discovery,
        protected WebBotAuthVerifier $verifier,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->config('enabled', false) || $this->discovery->servers()->visible()->isEmpty()) {
            return $next($request);
        }

        if ($request->is(...$this->patterns('except')) || $this->isAllowed($request)) {
            return $next($request);
        }

        if ($this->config('web_bot_auth.enabled', false)) {
            $status = $this->verifier->verify($request);

            if ($status === SignatureStatus::Valid) {
                return $this->pass($request, $next);
            }

            if ($status === SignatureStatus::Invalid) {
                return $this->gate($request);
            }
        }

        if (! $this->discovery->isBot($request)) {
            return $next($request);
        }

        $letUnsignedThrough = $this->config('web_bot_auth.enabled', false)
            && $this->config('web_bot_auth.unsigned', 'block') === 'pass';

        if ($letUnsignedThrough || $this->config('response', 403) === 'pass') {
            return $this->pass($request, $next);
        }

        return $this->gate($request);
    }

    protected function pass(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        LinkHeader::append($response, $this->discovery->linkValues($this->rel()));

        return $response;
    }

    protected function gate(Request $request): Response
    {
        $status = (int) $this->config('response', 403) === 402 ? 402 : 403;
        $servers = $this->discovery->servers()->visible()->values();

        $response = $request->expectsJson()
            ? new JsonResponse([
                'message' => 'Automated access to this page is not available. Use the MCP server instead.',
                'mcp' => $servers->map(fn (ServerDescriptor $server) => array_filter([
                    'name' => $server->name,
                    'title' => $server->title,
                    'url' => $server->url,
                    'server_card' => $this->cardUrl($server),
                ]))->all(),
            ], $status)
            : new IlluminateResponse($this->body($servers->all()), $status, ['Content-Type' => 'text/plain; charset=UTF-8']);

        LinkHeader::append($response, $this->discovery->linkValues($this->rel()));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    /**
     * @param  list<ServerDescriptor>  $servers
     */
    protected function body(array $servers): string
    {
        $lines = ['Automated access to this page is not available.', 'This site offers its content over MCP (Model Context Protocol):', ''];

        foreach ($servers as $server) {
            $lines[] = "- {$server->title}: {$server->url}";

            if ($card = $this->cardUrl($server)) {
                $lines[] = "  server card: {$card}";
            }
        }

        return implode("\n", $lines)."\n";
    }

    protected function cardUrl(ServerDescriptor $server): ?string
    {
        return $this->discovery->emitterEnabled('server_card') ? $this->discovery->cardUrl($server) : null;
    }

    protected function isAllowed(Request $request): bool
    {
        $userAgent = strtolower((string) $request->userAgent());

        return $userAgent !== '' && Arr::first(
            $this->patterns('allow'),
            fn (string $agent) => str_contains($userAgent, strtolower($agent)),
        ) !== null;
    }

    /**
     * @return list<string>
     */
    protected function patterns(string $key): array
    {
        return array_values(array_map(
            fn ($pattern) => $pattern === '/' ? '/' : ltrim((string) $pattern, '/'),
            (array) $this->config($key, []),
        ));
    }

    protected function rel(): string
    {
        return (string) $this->discovery->emitterOption('link_header', 'rel', 'mcp');
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return config("mcp-discovery.bot_gate.{$key}", $default);
    }
}
