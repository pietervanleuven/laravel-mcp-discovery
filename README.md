# Make your Laravel MCP servers discoverable by AI agents

[![Latest Version on Packagist](https://img.shields.io/packagist/v/pietervanleuven/laravel-mcp-discovery.svg?style=flat-square)](https://packagist.org/packages/pietervanleuven/laravel-mcp-discovery)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/pietervanleuven/laravel-mcp-discovery/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/pietervanleuven/laravel-mcp-discovery/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/pietervanleuven/laravel-mcp-discovery.svg?style=flat-square)](https://packagist.org/packages/pietervanleuven/laravel-mcp-discovery)

Sites spend effort blocking AI crawlers. Crawlers spend effort getting around the blocks. Both lose.

[`laravel/mcp`](https://laravel.com/docs/mcp) already makes it easy to expose structured tools (`search`, `get_article`, …) over MCP. What's missing is the **signpost**: a way for an agent landing on your site to learn *"there's an MCP server here, use that"*.

This package adds the signpost. It reads the servers you registered with `Mcp::web()` and publishes them through the discovery standards that are emerging. You can switch each one on or off in config.

```php
#[Discoverable(name: 'com.example/blog', primary: true)]
class BlogServer extends Server { /* ... */ }
```

```bash
$ curl https://example.com/.well-known/mcp-server-card
{ "name": "com.example/blog", "remotes": [{ "type": "streamable-http", "url": "https://example.com/mcp/blog" }], ... }
```

> **Status: pre-release (0.x).** The standards underneath are still moving, so expect config keys and output formats to follow them.

## What it does

| Feature | Standard | Default |
| --- | --- | --- |
| Server Cards at `/.well-known/mcp-server-card[/{key}]` | MCP [SEP-2127](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2127) (in review) | on |
| `llms.txt` section listing your MCP servers | [llms.txt](https://llmstxt.org/) | on |
| `robots.txt` MCP pointers + Content Signals | [Content Signals](https://contentsignals.org/), [IETF aipref](https://datatracker.ietf.org/wg/aipref/about/) | off |
| `Link: <…>; rel="mcp"` header on HTML responses | Proposal (not IANA-registered) | off |
| `Accept: text/markdown` → always send the MCP `Link` header | Mirrors Cloudflare's Markdown for Agents | off |
| A2A Agent Card at `/.well-known/agent-card.json` | [A2A](https://a2a-protocol.org/) | off |
| **Bot gate**: redirect-instead-of-reject (`403`/`402` + `Link` + short body) | Proposal | off |
| Web Bot Auth signature verification (signed agents get through, unsigned get gated) | IETF [webbotauth](https://datatracker.ietf.org/doc/draft-meunier-web-bot-auth-architecture/) | off |
| `server.json` generation for the [MCP Registry](https://registry.modelcontextprotocol.io/) | MCP Registry | command |

Each standard lives in its own **emitter** class, so a spec change touches only one file.

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- `laravel/mcp` ^1.0
- `ext-sodium` if you use Web Bot Auth. It ships with most PHP builds.

## Installation

```bash
composer require pietervanleuven/laravel-mcp-discovery
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="mcp-discovery-config"
```

Make sure `APP_URL` is set to your public, canonical URL. **All discovery URLs are built from `APP_URL`, never from the request's `Host` header**, so publicly cached cards can't be poisoned.

## Quick start

### 1. Register your server as usual

Nothing about how `laravel/mcp` works changes:

```php
// routes/ai.php
use App\Mcp\Servers\BlogServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/blog', BlogServer::class)
    ->middleware('throttle:mcp');
```

### 2. Mark it `#[Discoverable]`

```php
namespace App\Mcp\Servers;

use App\Mcp\Tools\GetArticle;
use App\Mcp\Tools\SearchArticles;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use PieterVanLeuven\McpDiscovery\Attributes\Discoverable;

#[Name('Blog')]
#[Version('1.0.0')]
#[Instructions('Search and read articles from example.com.')]
#[Discoverable(name: 'com.example/blog', primary: true)]
class BlogServer extends Server
{
    protected array $tools = [
        SearchArticles::class,
        GetArticle::class,
    ];
}
```

### 3. Check what's published

```bash
php artisan mcp-discovery:list
php artisan mcp-discovery:validate
```

```
+------+------------------+---------+------------------------------+------+---------+--------+-------+
| Key  | Name             | Version | URL                          | Auth | Primary | Public | Tools |
+------+------------------+---------+------------------------------+------+---------+--------+-------+
| blog | com.example/blog | 1.0.0   | https://example.com/mcp/blog | none | yes     | yes    | 2     |
+------+------------------+---------+------------------------------+------+---------+--------+-------+
```

That's it. You now have:

```http
GET /.well-known/mcp-server-card
Content-Type: application/json
Access-Control-Allow-Origin: *
Cache-Control: max-age=3600, public
```

```json
{
    "$schema": "https://static.modelcontextprotocol.io/schemas/v1/server-card.schema.json",
    "name": "com.example/blog",
    "version": "1.0.0",
    "title": "Blog",
    "description": "Search and read articles from example.com.",
    "websiteUrl": "https://example.com",
    "remotes": [
        {
            "type": "streamable-http",
            "url": "https://example.com/mcp/blog",
            "supportedProtocolVersions": ["2026-07-28"]
        }
    ]
}
```

and `GET /llms.txt`:

```markdown
# Example

> Example exposes structured tools for AI agents over MCP.

<!-- mcp-discovery:start -->
## MCP servers

This site offers its content over the Model Context Protocol (MCP). Agents should connect to these servers instead of scraping HTML.

- [Blog](https://example.com/mcp/blog): Search and read articles from example.com. Streamable HTTP endpoint. Server card: https://example.com/.well-known/mcp-server-card
<!-- mcp-discovery:end -->
```

## Where metadata comes from

Each value is resolved from these sources in order. **Later sources win.**

1. `laravel/mcp` attributes and properties on the server: `#[Name]` → `title`, `#[Version]`, `#[Instructions]`, `#[Title]`, `#[Description]`, `#[Icon]`
2. `#[Discoverable(...)]`
3. The server's entry in `config('mcp-discovery.servers')`

| Field | Default when nothing is set |
| --- | --- |
| `key` (URL segment) | Class name without `Server`, kebab-cased: `BlogServer` → `blog` |
| `name` (registry name) | Reverse-DNS of `APP_URL` + key: `https://example.com` → `com.example/blog` |
| `title` | `#[Title]`, else `#[Name]` |
| `description` | `#[Description]`, else the first paragraph of the instructions (max 100 chars) |
| `url` | Absolute URL of the `Mcp::web()` route (domain routes supported) |
| `protocol_versions` | What the `laravel/mcp` server advertises |
| `auth` | Inferred from route middleware (see below) |
| `primary` | The first server, if none is marked |

**Auth inference.** `auth:api` (or any non-Sanctum guard) combined with `Mcp::oauthRoutes()` means `oauth`: the card stays minimal and clients discover OAuth through the `WWW-Authenticate` header, as the MCP spec describes. `auth:sanctum` (or `auth` without OAuth routes) means `bearer`: the card lists a required secret `Authorization` header in `remotes[].headers`.

### `#[Discoverable]` reference

```php
#[Discoverable(
    key: 'blog',                          // /.well-known/mcp-server-card/blog
    name: 'com.example/blog',             // reverse-DNS, used by the MCP Registry
    title: 'Example Blog',
    description: 'Search and read articles.',
    primary: true,                        // served at /.well-known/mcp-server-card
    public: true,                         // false = only for logged-in users / verified agents
    websiteUrl: 'https://example.com/blog',
    repository: ['url' => 'https://github.com/acme/site', 'source' => 'github'],
)]
```

### Configuring servers explicitly

Prefer config over attributes, or need to expose a server that lives elsewhere? Replace `'auto'` with a map:

```php
// config/mcp-discovery.php
'servers' => [
    'blog' => [
        'class' => App\Mcp\Servers\BlogServer::class,
        'name' => 'com.example/blog',
        'primary' => true,
    ],

    // Not registered with Mcp::web() in this app: give a URL.
    'search' => [
        'class' => App\Mcp\Servers\SearchServer::class,
        'url' => 'https://search.example.com/mcp',
        'auth' => 'oauth', // 'none' | 'oauth' | 'bearer'
    ],

    // Shorthand: key derived from the class.
    App\Mcp\Servers\ShopServer::class,
],
```

In explicit mode `#[Discoverable]` is optional, and config values override it. Any descriptor field can be set: `name`, `title`, `description`, `version`, `url`, `website_url`, `repository`, `icons`, `protocol_versions`, `auth`, `primary`, `public`.

## Emitters

Every emitter is configured under `mcp-discovery.emitters.{key}` and has an `enabled` flag and a `class`.

### Server Cards

```php
'server_card' => [
    'enabled' => true,
    'path' => '.well-known/mcp-server-card',  // SEP-2127 revisions disagree; follow whichever is merged
    'index_path' => null,                      // e.g. '.well-known/mcp/server-cards.json'
    'cache' => 3600,
    'include_capabilities' => false,
],
```

- `GET /{path}` serves the primary server. `GET /{path}/{key}` serves any server.
- `index_path` adds one document listing every card: `{"servers": [ {...card, "_links": {"self": "…"}} ]}`.
- `include_capabilities` adds `capabilities`, `tools`, `resources` and `prompts` (name, title, description) so agents can see what a server does before connecting. It is off by default because not every site wants its tool surface public. Tools that use `shouldRegister()` are respected.
- Non-public servers return `404` unless the request comes from a logged-in user or a verified Web Bot Auth agent, and are then served with `Cache-Control: private, no-store`.

### `llms.txt`

```php
'llms_txt' => [
    'enabled' => true,
    'mode' => 'route',   // 'route' | 'file'
    'base' => null,      // e.g. resource_path('llms.txt')
],
```

There are two ways to ship it, because a static `public/llms.txt` is served by your web server before Laravel ever runs:

**`route` mode (default).** The package serves `/llms.txt` dynamically. Put your hand-written content in a file outside `public/` and point `base` at it; the MCP section is appended.

```php
'base' => resource_path('llms.txt'),
```

**`file` mode.** Keep your static `public/llms.txt` and let the package maintain a marked section in it:

```bash
php artisan mcp-discovery:write            # writes/updates the section
php artisan mcp-discovery:write --dry-run  # prints the result
```

The section sits between `<!-- mcp-discovery:start -->` and `<!-- mcp-discovery:end -->`. Everything outside the markers is left alone, and re-running the command is idempotent. Add it to your deploy script. `mcp-discovery:validate` warns when the file is out of date, or when a static file shadows the route.

### `robots.txt`

```php
'robots_txt' => [
    'enabled' => true,
    'mode' => 'file',  // Laravel ships public/robots.txt, so 'file' is the usual choice
    'content_signals' => ['search' => 'yes', 'ai-input' => 'yes', 'ai-train' => 'no'],
],
```

```bash
php artisan mcp-discovery:write
```

```
User-agent: *
Disallow:

# mcp-discovery:start
# MCP server (com.example/blog): https://example.com/mcp/blog
# MCP server card: https://example.com/.well-known/mcp-server-card
User-agent: *
Content-Signal: search=yes, ai-input=yes, ai-train=no
# mcp-discovery:end
```

`robots.txt` has no directive for MCP, so the pointers are comments. The emitter never adds `Allow`/`Disallow` rules: crawlers merge groups for the same user agent, and an extra `Allow: /` could override your own rules.

### `Link` header

```php
'link_header' => [
    'enabled' => true,
    'rel' => 'mcp',
    'only' => 'bots',  // 'bots' | 'all'
],
```

HTML responses in the `web` middleware group get:

```http
Link: <https://example.com/mcp/blog>; rel="mcp"; title="Blog"
Link: <https://example.com/.well-known/mcp-server-card>; rel="service-desc"; type="application/json"
```

`rel="service-desc"` is [RFC 8631](https://www.rfc-editor.org/rfc/rfc8631). `rel="mcp"` isn't registered yet. With `only => 'bots'`, the header is added only for requests the bot detector flags. If you put a shared cache in front of the site, either use `'all'` or make sure the cache varies on `User-Agent`.

### Markdown hint

```php
'markdown_hint' => ['enabled' => true],
```

Requests sending `Accept: text/markdown` are almost always agents. They get the MCP `Link` header on every response, whatever its content type, plus `Vary: Accept`.

### A2A Agent Card

```php
'a2a_agent_card' => [
    'enabled' => true,
    'url' => 'https://example.com/a2a',  // your A2A endpoint
],
```

Serves `/.well-known/agent-card.json` built from the primary server, with each tool as a skill. **Only enable this if you actually run an A2A endpoint.** The MCP endpoint does not speak A2A, and `mcp-discovery:validate` warns when `url` is missing.

## Bot gate: redirect instead of reject

Instead of blocking AI crawlers (and watching them rotate user agents), tell them where the good stuff is:

```php
'bot_gate' => [
    'enabled' => true,
    'response' => 403,  // 403 | 402 | 'pass'
    'except' => ['/', 'robots.txt', 'llms.txt', '.well-known/*'],
    'allow' => ['Googlebot', 'Bingbot', 'DuckDuckBot', 'Applebot', 'YandexBot'],
    'user_agents' => [],  // extra UA fragments to treat as AI bots
],
```

```bash
$ curl -i -A "ClaudeBot/1.0" https://example.com/articles/hello
HTTP/1.1 403 Forbidden
Content-Type: text/plain; charset=UTF-8
Cache-Control: no-store, private
Link: <https://example.com/mcp/blog>; rel="mcp"; title="Blog"
Link: <https://example.com/.well-known/mcp-server-card>; rel="service-desc"; type="application/json"

Automated access to this page is not available.
This site offers its content over MCP (Model Context Protocol):

- Blog: https://example.com/mcp/blog
  server card: https://example.com/.well-known/mcp-server-card
```

Clients that send `Accept: application/json` get the same information as JSON. `'response' => 'pass'` serves the page as normal and only adds the `Link` header. `402` is there for sites experimenting with paid access.

Search crawlers in `allow` always get HTML. Paths in `except` are never gated. If there are no public servers, the gate does nothing.

### Custom bot detection

The built-in `UserAgentDetector` is deliberately simple. Swap it for anything that implements `Detector`, for example Cloudflare's verified-bot signal:

```php
namespace App\Bots;

use Illuminate\Http\Request;
use PieterVanLeuven\McpDiscovery\Bots\Detector;
use PieterVanLeuven\McpDiscovery\Bots\UserAgentDetector;

class CloudflareDetector implements Detector
{
    public function __construct(protected UserAgentDetector $fallback) {}

    public function isBot(Request $request): bool
    {
        // Requires a Transform Rule that copies cf.verified_bot_category into this header.
        if ($request->header('X-Verified-Bot-Category') === 'AI Crawler') {
            return true;
        }

        return $this->fallback->isBot($request);
    }
}
```

```php
'bot_gate' => ['detector' => App\Bots\CloudflareDetector::class, /* ... */],
```

### Web Bot Auth

Agents that sign their requests with [HTTP Message Signatures](https://www.rfc-editor.org/rfc/rfc9421) (Web Bot Auth) can prove who they are. Enable verification and signed agents get through, while everyone else pretending to be them gets gated:

```php
'bot_gate' => [
    'enabled' => true,
    'web_bot_auth' => [
        'enabled' => true,
        'unsigned' => 'block',   // 'block' = gate unsigned bots; 'pass' = let them through with the Link header
        'directory_cache' => 3600,
        'max_age' => 300,
    ],
],
```

| Request | Result |
| --- | --- |
| Valid signature | Page served, plus `Link` header. Non-public server cards become visible. |
| Signature present but invalid, expired, for another host or with an unknown key | Gated |
| Unsigned, detected as a bot | Gated (`block`) or served with the `Link` header (`pass`) |
| Unsigned, not a bot | Served normally |

The verifier fetches the agent's key directory from `Signature-Agent` + `/.well-known/http-message-signatures-directory`, matches `keyid` to a JWK thumbprint (RFC 7638) and checks the Ed25519 signature over the covered components (`@authority`, `signature-agent`, `@method`, `@path`, headers, …). Key directories are cached, and only `https` agents are accepted.

### Registering the middleware yourself

By default, `BotGate` and `AddDiscoveryHeaders` are appended to the `web` group, but only when a feature that needs them is enabled. To control placement yourself:

```php
// config/mcp-discovery.php
'register_middleware' => false,
```

```php
// bootstrap/app.php
use PieterVanLeuven\McpDiscovery\Http\Middleware\AddDiscoveryHeaders;
use PieterVanLeuven\McpDiscovery\Http\Middleware\BotGate;

->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [BotGate::class, AddDiscoveryHeaders::class]);
})
```

## Publishing to the MCP Registry

```bash
php artisan mcp-discovery:publish-registry        # primary server
php artisan mcp-discovery:publish-registry shop --output=registry/shop.json
```

This writes a `server.json` in the registry format and prints the [`mcp-publisher`](https://github.com/modelcontextprotocol/registry) commands to run. The registry verifies you own the namespace, so `com.example/*` needs DNS or HTTP verification of `example.com`. That's why names default to the reverse-DNS form of `APP_URL`.

## Artisan commands

| Command | What it does |
| --- | --- |
| `mcp-discovery:list [--json]` | Servers and emitters, as the package sees them |
| `mcp-discovery:validate` | Checks names, versions, HTTPS, description length, duplicate primaries, stale or shadowed `llms.txt`/`robots.txt`. Exits non-zero on errors, so it can run in CI. |
| `mcp-discovery:write [--dry-run]` | Updates `public/llms.txt` and `public/robots.txt` for emitters in `file` mode |
| `mcp-discovery:publish-registry [server] [--output=]` | Generates `server.json` for the MCP Registry |

## Using the descriptors in your own code

```php
use PieterVanLeuven\McpDiscovery\Facades\McpDiscovery;

$primary = McpDiscovery::servers()->primary();

$primary->name;          // 'com.example/blog'
$primary->url;           // 'https://example.com/mcp/blog'
$primary->tools();       // [['name' => 'search-articles', 'title' => ..., 'description' => ...], ...]

McpDiscovery::cardUrl($primary);   // 'https://example.com/.well-known/mcp-server-card'
McpDiscovery::linkValues();        // ready-made Link header values
```

For example, advertise the server in your HTML `<head>` too:

```blade
@use('PieterVanLeuven\McpDiscovery\Facades\McpDiscovery')

@foreach (McpDiscovery::servers()->visible() as $server)
    <link rel="mcp" href="{{ $server->url }}" title="{{ $server->title }}">
@endforeach
```

## Writing your own emitter

A new discovery standard means adding one class. Extend `Emitter`, register routes if it serves something, and implement `ProvidesLinks` or `WritesPublicFile` if it adds headers or file sections:

```php
namespace App\Discovery;

use Illuminate\Routing\Router;
use PieterVanLeuven\McpDiscovery\Emitters\Emitter;

class McpManifestEmitter extends Emitter
{
    public function routes(Router $router): void
    {
        $router->get('.well-known/mcp', fn () => response()->json([
            'servers' => $this->discovery->servers()->visible()
                ->map(fn ($server) => ['name' => $server->name, 'url' => $server->url])
                ->values(),
        ]));
    }
}
```

```php
'emitters' => [
    // ...
    'mcp_manifest' => ['enabled' => true, 'class' => App\Discovery\McpManifestEmitter::class],
],
```

The same works for replacing a built-in emitter: point its `class` at your subclass.

## Caveats

- **Route caching.** With `php artisan route:cache`, `laravel/mcp` doesn't load `routes/ai.php`, so the package finds servers by scanning the cached routes instead. Discovery routes are registered at boot and cached along with your own. Re-run `route:cache` after changing emitter config.
- **Octane.** Services are scoped, so descriptors are rebuilt per request.
- **One route per class.** If a server class is registered at several URLs, the first one is published. Use explicit config with a `url` to pick another.
- **App routes win.** If your app defines its own `/llms.txt` or `/robots.txt` route, it overrides the package's.

## Roadmap

- [x] Server Cards, `list`/`validate` commands
- [x] `llms.txt`, `robots.txt`, `Link` header
- [x] Bot gate with a pluggable detector
- [x] Web Bot Auth verification, A2A card, registry `server.json`
- [ ] WebMCP bridge script (`navigator.modelContext`)
- [ ] SEP-1960 `/.well-known/mcp` manifest emitter (if it gets merged)
- [ ] x402 / `402` payment hook (probably a separate package)
- [ ] `mcp-discovery:cache` for fully static cards

## Testing

```bash
composer test       # Pest
composer analyse    # PHPStan (level 6)
composer format     # Pint
```

## Changelog

See [CHANGELOG](CHANGELOG.md).

## Contributing

See [CONTRIBUTORS](CONTRIBUTORS.md). If you're an AI coding agent, read [AGENTS.md](AGENTS.md) first.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Pieter Van Leuven](https://github.com/pietervanleuven)
- [All Contributors](../../contributors)

## References

- Laravel MCP: https://laravel.com/docs/mcp
- SEP-2127 MCP Server Cards: https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2127
- MCP Registry: https://registry.modelcontextprotocol.io/
- llms.txt: https://llmstxt.org/
- Content Signals: https://contentsignals.org/
- IETF AI Preferences: https://datatracker.ietf.org/wg/aipref/about/
- Web Bot Auth: https://datatracker.ietf.org/doc/draft-meunier-web-bot-auth-architecture/
- RFC 9421 HTTP Message Signatures: https://www.rfc-editor.org/rfc/rfc9421
- A2A: https://a2a-protocol.org/
- Cloudflare Markdown for Agents: https://developers.cloudflare.com/fundamentals/reference/markdown-for-agents
- WebMCP: https://webmachinelearning.github.io/webmcp/

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
