# AGENTS.md

Guidance for AI coding agents (Claude Code, Codex, Cursor, …) working in this repository. Humans should read [CONTRIBUTORS.md](CONTRIBUTORS.md) as well.

## What this package is

A Laravel package that makes servers built with `laravel/mcp` discoverable. It reads the servers registered with `Mcp::web()` and publishes them through discovery standards: Server Cards, `llms.txt`, `robots.txt`, `Link` headers, the A2A card, and the MCP Registry `server.json`. It also offers a bot gate that points crawlers at MCP instead of serving HTML.

Layout mirrors [spatie/package-skeleton-laravel](https://github.com/spatie/package-skeleton-laravel): `src/`, `config/`, `tests/` (Pest + Orchestra Testbench), `.github/workflows/`.

## Commands

```bash
composer install
vendor/bin/pest                 # all tests (random order, must stay green)
vendor/bin/pest --filter=BotGate
vendor/bin/phpstan analyse      # level 6, must report no errors
vendor/bin/pint                 # code style (Laravel preset)
```

Run all three before you consider a change done.

## Architecture in one paragraph

`ServerRepository` turns each `laravel/mcp` web server into an immutable `ServerDescriptor`. **Emitters only read descriptors**; they never touch `laravel/mcp` directly. `McpDiscovery` is the entry point: it builds emitters from config, exposes the repository and the bot detector, and assembles `Link` values. HTTP lives in `Http/` (the `WellKnownController` and `TextFileController` controllers, plus the `BotGate` and `AddDiscoveryHeaders` middleware). `Bots/` holds the detector contract, the UA list and the Web Bot Auth verifier.

```
src/
├── McpDiscoveryServiceProvider.php  # spatie/laravel-package-tools; registers routes + middleware at boot
├── McpDiscovery.php                 # emitters, links, detector
├── ServerRepository.php             # laravel/mcp → ServerDescriptor
├── ServerDescriptor.php             # value object; tools/resources/prompts are lazy
├── Attributes/Discoverable.php
├── Emitters/                        # one class per standard
│   ├── Emitter.php                  # base: key, config, enabled(), routes()
│   ├── Contracts/                   # ProvidesLinks, WritesPublicFile
│   ├── TextFileEmitter.php          # marker-based sections, route vs file mode
│   └── …Emitter.php
├── Http/{Controllers,Middleware}/
├── Bots/                            # Detector, UserAgentDetector, WebBotAuthVerifier
├── Console/                         # list, validate, write, publish-registry
├── Enums/ Exceptions/ Support/      # Support: Url, LinkHeader, StructuredFields
```

## How laravel/mcp is read (fragile spots)

`laravel/mcp` exposes no public "list web servers with their class" API, so these spots depend on its internals. Re-check them when bumping `laravel/mcp`:

- `Registrar::servers()` returns `Route` objects for web servers. The server class is recovered from the route closure's **static variable `$serverClass`** (`ServerRepository::serverClassFor()`).
- With cached routes, the registrar is empty and closures are serialized. The repository falls back to scanning the router and unserializing `SerializableClosure` actions.
- Metadata comes from `Server::createContext()` (public), with the server built as `make($class, ['transport' => new FakeTransporter])`.

If `laravel/mcp` changes any of these, fix it in `ServerRepository` only.

## Rules

- **One standard, one emitter.** A new or changed spec goes into its own `Emitter` subclass, with config under `mcp-discovery.emitters.{key}`. Don't add format logic to controllers or middleware.
- **New emitters default to `enabled => false`**, except when they are cheap, read-only and uncontroversial (the server card and `llms.txt` route are the only ones that default on).
- **URLs come from `Support\Url::to()`** (i.e. `APP_URL`), never from `url()` or the request host. Cards are publicly cached; trusting `Host` would allow cache poisoning.
- **Non-public servers** (`public: false`) must never appear in `llms.txt`, `robots.txt`, `Link` headers, the bot gate body, or the A2A card. Only `WellKnownController` may show them, and only to authenticated users or verified agents, with `Cache-Control: private, no-store`.
- **Never add `Allow`/`Disallow` rules to `robots.txt`.** Merged groups could override the site's own rules.
- **Keep descriptors cheap.** Anything that instantiates tools, resources or prompts must stay lazy (see `ServerDescriptor::primitives()`), because the bot gate resolves descriptors on web requests.
- **Web Bot Auth is security code.** Every new branch in `WebBotAuthVerifier` needs a test that proves a bad signature is rejected. Failures return `SignatureStatus::Invalid`; never throw.
- Services are bound `scoped` (Octane). Don't turn them into singletons.
- Don't hard-code spec paths. Paths and versions that are still in flux go in config.
- Match the surrounding style: no `declare(strict_types=1)` (Spatie style), typed properties, constructor promotion, short docblocks only where they add information.

## Tests

- Tests use real `laravel/mcp` servers in `tests/Fixtures/Servers`, registered in `TestCase::defineRoutes()`: `blog` (primary), `shop` (Sanctum → bearer), `ops` (non-public), and `hidden` (not discoverable).
- Routes and middleware are registered **at boot**, so config that affects them must be set **before** boot. Use the `withConfig([...])` helper from `tests/Pest.php`, which reboots the app. Setting `config()` inside a test is too late for routes and middleware.
- Web Bot Auth tests sign requests with a real Ed25519 keypair and fake the key directory with `Http::fake()`. Follow that pattern rather than mocking the verifier.
- Middleware registration is asserted against the HTTP kernel's groups, not the router's (the kernel re-syncs the router).

## Documentation

User-facing behaviour changes need a README update (config snippet + example output) and a `CHANGELOG.md` entry under *Unreleased*. Keep README examples truthful: generate the output from the test app instead of writing it by hand.
