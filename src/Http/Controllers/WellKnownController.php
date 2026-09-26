<?php

namespace PieterVanLeuven\McpDiscovery\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PieterVanLeuven\McpDiscovery\Bots\WebBotAuthVerifier;
use PieterVanLeuven\McpDiscovery\Emitters\A2aAgentCardEmitter;
use PieterVanLeuven\McpDiscovery\Emitters\ServerCardEmitter;
use PieterVanLeuven\McpDiscovery\McpDiscovery;
use PieterVanLeuven\McpDiscovery\ServerDescriptor;

class WellKnownController
{
    public function __construct(
        protected McpDiscovery $discovery,
        protected WebBotAuthVerifier $verifier,
    ) {}

    public function serverCard(Request $request, ?string $server = null): JsonResponse
    {
        $emitter = $this->serverCardEmitter();
        $descriptor = $server === null
            ? $this->discovery->servers()->primary()
            : $this->discovery->servers()->find($server);

        if ($descriptor === null || ! $this->canSee($request, $descriptor)) {
            abort(404);
        }

        return $this->json($emitter->card($descriptor), $emitter->cacheSeconds(), $descriptor->public);
    }

    public function serverCardIndex(Request $request): JsonResponse
    {
        $emitter = $this->serverCardEmitter();
        $includePrivate = $this->canSeePrivate($request);

        $cards = $this->discovery->servers()
            ->visible($includePrivate)
            ->map(fn (ServerDescriptor $server) => [
                ...$emitter->card($server),
                '_links' => ['self' => $emitter->url($server)],
            ])
            ->values()
            ->all();

        return $this->json(['servers' => $cards], $emitter->cacheSeconds(), ! $includePrivate);
    }

    public function agentCard(): JsonResponse
    {
        $emitter = $this->discovery->emitter('a2a_agent_card');
        $card = $emitter instanceof A2aAgentCardEmitter ? $emitter->card() : null;

        abort_if($card === null, 404);

        return $this->json($card, (int) $this->discovery->emitterOption('server_card', 'cache', 3600));
    }

    protected function serverCardEmitter(): ServerCardEmitter
    {
        $emitter = $this->discovery->emitter('server_card');

        abort_unless($emitter instanceof ServerCardEmitter && $emitter->enabled(), 404);

        return $emitter;
    }

    protected function canSee(Request $request, ServerDescriptor $server): bool
    {
        return $server->public || $this->canSeePrivate($request);
    }

    /**
     * Non-public servers are listed for authenticated users and verified (signed) agents.
     */
    protected function canSeePrivate(Request $request): bool
    {
        if ($request->user() !== null) {
            return true;
        }

        return config('mcp-discovery.bot_gate.web_bot_auth.enabled', false)
            && $this->verifier->isVerified($request);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function json(array $data, int $cacheSeconds, bool $public = true): JsonResponse
    {
        return new JsonResponse($data, 200, [
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => $public ? "public, max-age={$cacheSeconds}" : 'private, no-store',
        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
