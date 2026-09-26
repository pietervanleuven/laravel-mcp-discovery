<?php

use PieterVanLeuven\McpDiscovery\Bots\UserAgentDetector;
use PieterVanLeuven\McpDiscovery\Emitters\A2aAgentCardEmitter;
use PieterVanLeuven\McpDiscovery\Emitters\LinkHeaderEmitter;
use PieterVanLeuven\McpDiscovery\Emitters\LlmsTxtEmitter;
use PieterVanLeuven\McpDiscovery\Emitters\MarkdownHintEmitter;
use PieterVanLeuven\McpDiscovery\Emitters\RobotsTxtEmitter;
use PieterVanLeuven\McpDiscovery\Emitters\ServerCardEmitter;

return [

    /*
     * Which MCP servers to expose.
     *
     * 'auto' exposes every server registered with Mcp::web() that carries the
     * #[Discoverable] attribute. Pass an array to configure servers explicitly;
     * explicit entries override attribute values.
     */
    'servers' => 'auto',

    // 'servers' => [
    //     'blog' => [
    //         'class' => App\Mcp\Servers\BlogServer::class,
    //         'name' => 'com.example/blog',
    //         'primary' => true,
    //         'public' => true, // false = only listed for authenticated users or signed agents
    //     ],
    // ],

    /*
     * Values applied to every server unless the server overrides them.
     */
    'defaults' => [
        'website_url' => env('APP_URL'),

        // e.g. ['url' => 'https://github.com/acme/site', 'source' => 'github']
        'repository' => null,

        // e.g. [['src' => 'https://example.com/icon.png', 'mimeType' => 'image/png', 'sizes' => ['64x64']]]
        'icons' => [],

        // null = use the versions the laravel/mcp server itself advertises.
        'protocol_versions' => null,
    ],

    /*
     * Every discovery standard lives in its own emitter. Swap the 'class' to
     * customise one, or add your own entry with a class that extends
     * PieterVanLeuven\McpDiscovery\Emitters\Emitter.
     */
    'emitters' => [

        // MCP Server Cards (SEP-2127). The spec path is still in flux.
        'server_card' => [
            'enabled' => true,
            'class' => ServerCardEmitter::class,
            'path' => '.well-known/mcp-server-card',

            // Optional index listing every card, e.g. '.well-known/mcp/server-cards.json'.
            'index_path' => null,

            // Seconds for the Cache-Control max-age header.
            'cache' => 3600,

            // List tool/resource/prompt names and descriptions in the card.
            'include_capabilities' => false,
        ],

        // Link: <https://example.com/mcp/blog>; rel="mcp" on HTML responses.
        'link_header' => [
            'enabled' => false,
            'class' => LinkHeaderEmitter::class,
            'rel' => 'mcp',
            'only' => 'bots', // 'bots' | 'all'
        ],

        // An "MCP servers" section in llms.txt.
        'llms_txt' => [
            'enabled' => true,
            'class' => LlmsTxtEmitter::class,

            // 'route' serves /llms.txt dynamically (only reached when public/llms.txt does not exist).
            // 'file' leaves routing alone; run `php artisan mcp-discovery:write` to update public/llms.txt.
            'mode' => 'route',

            // Content placed above the MCP section in 'route' mode, e.g. resource_path('llms.txt').
            'base' => null,
        ],

        // MCP pointer comments and Content Signals in robots.txt.
        'robots_txt' => [
            'enabled' => false,
            'class' => RobotsTxtEmitter::class,
            'mode' => 'file', // 'file' | 'route' (Laravel ships public/robots.txt, so 'file' is the usual choice)
            'base' => null,
            'content_signals' => ['search' => 'yes', 'ai-input' => 'yes', 'ai-train' => 'no'],
        ],

        // Requests with `Accept: text/markdown` always get the MCP Link header.
        'markdown_hint' => [
            'enabled' => false,
            'class' => MarkdownHintEmitter::class,
        ],

        // A2A Agent Card at /.well-known/agent-card.json. Only enable this when you run an A2A endpoint.
        'a2a_agent_card' => [
            'enabled' => false,
            'class' => A2aAgentCardEmitter::class,
            'path' => '.well-known/agent-card.json',
            'url' => null, // your A2A endpoint
            'protocol_version' => '0.3.0',
        ],
    ],

    /*
     * Redirect-instead-of-reject: detected bots get a short response that
     * points them at your MCP server instead of the HTML page.
     */
    'bot_gate' => [
        'enabled' => false,
        'detector' => UserAgentDetector::class,

        // 403 | 402 | 'pass' ('pass' serves the page and only adds the Link header)
        'response' => 403,

        // Paths that are never gated.
        'except' => ['/', 'robots.txt', 'llms.txt', '.well-known/*'],

        // User agents that always get HTML (search crawlers).
        'allow' => ['Googlebot', 'Bingbot', 'DuckDuckBot', 'Applebot', 'YandexBot'],

        // Extra user agent fragments the UserAgentDetector treats as AI bots.
        'user_agents' => [],

        // Web Bot Auth (HTTP Message Signatures). Verified agents get the page plus the Link header.
        'web_bot_auth' => [
            'enabled' => false,
            'unsigned' => 'block', // 'block' = gate unsigned bots, 'pass' = let them through with the Link header
            'directory_cache' => 3600,
            'max_age' => 300, // seconds a signature without `expires` stays valid
        ],
    ],

    /*
     * Push the discovery middleware onto the 'web' middleware group.
     * Disable to add AddDiscoveryHeaders / BotGate to your routes yourself.
     */
    'register_middleware' => true,
];
