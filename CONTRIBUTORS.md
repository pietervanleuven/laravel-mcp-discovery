# Contributing

Contributions are welcome and will be fully credited. Discovery standards for AI agents are young and change often, so issues that point at spec changes are as valuable as code.

Please read and understand this guide before creating an issue or pull request. If you're an AI coding agent, also read [AGENTS.md](AGENTS.md).

## Etiquette

This project is open source, and as such, the maintainers give their free time to build and maintain it. The code is freely available in the hope that it will be useful. It would be extremely unfair for someone to use this package and then complain or be abusive toward the maintainers.

Please be considerate when raising issues or presenting pull requests. Let's show the world that developers are civilized and selfless people.

The maintainers decide whether a contribution meets the quality standard and fits the project's direction.

## Viability

When requesting or submitting new features, first consider whether they are useful to others. Open source projects are used by many developers with different needs. Is the feature likely to be used by others?

**New discovery standards** are welcome as emitters. Please link the spec or draft, and say how settled it is (merged, in review, proposal). Anything still in flux ships disabled by default, with its paths configurable.

## Development setup

```bash
git clone git@github.com:pietervanleuven/laravel-mcp-discovery.git
cd laravel-mcp-discovery
composer install
composer test
```

You need PHP 8.3+ with `ext-sodium` (for the Web Bot Auth tests). No database or Laravel app is needed: the test suite boots a Testbench app with real `laravel/mcp` servers from `tests/Fixtures`.

| Command | What it runs |
| --- | --- |
| `composer test` | Pest, in random order |
| `composer analyse` | PHPStan / Larastan, level 6 |
| `composer format` | Laravel Pint |

### Trying it in a real Laravel app

Point a local app at your checkout with a Composer path repository:

```jsonc
// your-app/composer.json
"repositories": [
    { "type": "path", "url": "../laravel-mcp-discovery", "options": { "symlink": true } }
],
```

```bash
cd your-app
composer require laravel/mcp pietervanleuven/laravel-mcp-discovery:@dev
php artisan make:mcp-server BlogServer
php artisan vendor:publish --tag=ai-routes
```

Register the server in `routes/ai.php`, add `#[Discoverable]`, then:

```bash
php artisan mcp-discovery:list
php artisan mcp-discovery:validate
php artisan serve

curl -s http://127.0.0.1:8000/.well-known/mcp-server-card | jq
curl -s http://127.0.0.1:8000/llms.txt
curl -si -A "GPTBot/1.0" http://127.0.0.1:8000/some-page   # with bot_gate.enabled = true
```

To check the MCP endpoint behind the card, use the MCP Inspector: `php artisan mcp:inspector mcp/blog`.

## Procedure

Before filing an issue:

- Try to replicate the problem, to make sure it wasn't a coincidence.
- Check whether your feature suggestion has already been discussed in the project.
- Check the pull requests, to make sure the feature or fix isn't already in progress.
- Include the output of `php artisan mcp-discovery:list --json` and your `config/mcp-discovery.php`.

Before submitting a pull request:

- Check the codebase, to make sure the feature doesn't already exist.
- Check the pull requests, to make sure another contributor hasn't already made the feature or fix.

## Requirements

- **[PSR-12 / Laravel Pint](https://laravel.com/docs/pint)**: run `composer format`. CI also fixes style automatically on push.
- **Add tests.** Your patch won't be accepted without them. Security-relevant code (bot gate, Web Bot Auth, visibility of non-public servers) needs a test for the rejection path as well as the happy path.
- **PHPStan stays clean.** Don't add baseline entries for new code.
- **Document any change in behaviour.** Keep `README.md` up to date, with example output taken from a real run, and add a line to `CHANGELOG.md` under *Unreleased*.
- **Consider the release cycle.** We follow [SemVer v2.0.0](https://semver.org/). Don't break public APIs at random. While the package is 0.x, breaking changes go in minor versions and are called out in the changelog.
- **One pull request per feature.** If you want to do more than one thing, send multiple pull requests.
- **Send a coherent history.** Make sure each commit in your pull request is meaningful. If you made several intermediate commits while developing, [squash them](https://www.git-scm.com/book/en/v2/Git-Tools-Rewriting-History#Changing-Multiple-Commit-Messages) before submitting.

## Adding an emitter (checklist)

1. Add `src/Emitters/YourEmitter.php` extending `Emitter`. Implement `ProvidesLinks` or `WritesPublicFile` if it adds headers or file sections.
2. Add its entry to `config/mcp-discovery.php` with `enabled`, `class` and any paths, and a comment linking the spec.
3. Only read `ServerDescriptor`s, only list `visible()` servers, and build URLs with `Support\Url::to()`.
4. Add a feature test in `tests/Feature`, using `withConfig()` to enable it.
5. Add a row to the feature table and a section to `README.md`.

## Contributors

- [Pieter Van Leuven](https://github.com/pietervanleuven): creator and maintainer
- [All contributors](https://github.com/pietervanleuven/laravel-mcp-discovery/graphs/contributors)

Add yourself to this list in your first pull request.

**Happy coding**!
