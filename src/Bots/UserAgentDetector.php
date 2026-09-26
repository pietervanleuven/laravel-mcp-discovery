<?php

namespace PieterVanLeuven\McpDiscovery\Bots;

use Illuminate\Http\Request;

/**
 * Deliberately simple: matches known AI crawler and agent user agents.
 * Bind your own Detector for anything smarter (e.g. Cloudflare's verified bot headers).
 */
class UserAgentDetector implements Detector
{
    /** @var list<string> */
    public const AGENTS = [
        'AI2Bot',
        'Amazonbot',
        'anthropic-ai',
        'Applebot-Extended',
        'Bytespider',
        'CCBot',
        'ChatGPT-User',
        'Claude-SearchBot',
        'Claude-User',
        'ClaudeBot',
        'cohere-ai',
        'cohere-training-data-crawler',
        'Diffbot',
        'DuckAssistBot',
        'FacebookBot',
        'Google-CloudVertexBot',
        'GoogleOther',
        'GPTBot',
        'ImagesiftBot',
        'Kangaroo Bot',
        'meta-externalagent',
        'meta-externalfetcher',
        'MistralAI-User',
        'OAI-SearchBot',
        'omgili',
        'PanguBot',
        'Perplexity-User',
        'PerplexityBot',
        'Timpibot',
        'YouBot',
    ];

    public function isBot(Request $request): bool
    {
        $userAgent = strtolower((string) $request->userAgent());

        if ($userAgent === '') {
            return false;
        }

        foreach ($this->agents() as $agent) {
            if (str_contains($userAgent, strtolower($agent))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    protected function agents(): array
    {
        return [...self::AGENTS, ...(array) config('mcp-discovery.bot_gate.user_agents', [])];
    }
}
