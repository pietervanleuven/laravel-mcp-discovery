<?php

use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Registrar;
use PieterVanLeuven\McpDiscovery\Enums\AuthScheme;
use PieterVanLeuven\McpDiscovery\Exceptions\InvalidConfiguration;
use PieterVanLeuven\McpDiscovery\ServerRepository;
use PieterVanLeuven\McpDiscovery\Tests\Fixtures\Servers\BlogServer;
use PieterVanLeuven\McpDiscovery\Tests\Fixtures\Servers\HiddenServer;
use PieterVanLeuven\McpDiscovery\Tests\Fixtures\Servers\SecureServer;
use PieterVanLeuven\McpDiscovery\Tests\Fixtures\Servers\ShopServer;

function servers(): ServerRepository
{
    return app(ServerRepository::class);
}

it('discovers every Mcp::web() server marked #[Discoverable]', function () {
    expect(servers()->all()->keys()->all())->toBe(['blog', 'shop', 'ops']);
});

it('reads metadata from laravel/mcp attributes and #[Discoverable]', function () {
    $blog = servers()->find('blog');

    expect($blog)
        ->class->toBe(BlogServer::class)
        ->name->toBe('com.example/blog')
        ->version->toBe('1.0.0')
        ->title->toBe('Blog')
        ->description->toBe('Search and read articles from example.com.')
        ->url->toBe('https://example.com/mcp/blog')
        ->websiteUrl->toBe('https://example.com')
        ->primary->toBeTrue()
        ->public->toBeTrue()
        ->auth->toBe(AuthScheme::None)
        ->protocolVersions->not->toBeEmpty();
});

it('derives a key and a reverse-DNS name when none is given', function () {
    expect(servers()->find('shop'))->name->toBe('com.example/shop');
});

it('prefers the #[Discoverable] description', function () {
    expect(servers()->find('ops')->description)->toBe('Operations tooling.');
});

it('falls back to the first paragraph of the instructions for the description', function () {
    expect(servers()->find('shop')->description)->toBe('Use the shop tools to browse the catalogue.');
});

it('resolves tools lazily', function () {
    expect(servers()->find('blog')->tools())->toBe([
        ['name' => 'search-articles', 'title' => 'Search Articles', 'description' => 'Full-text search over published articles.'],
        ['name' => 'get-article', 'title' => 'Get article', 'description' => 'Fetch one article as Markdown.'],
    ]);
});

it('infers bearer auth from sanctum middleware', function () {
    expect(servers()->find('shop')->auth)->toBe(AuthScheme::Bearer);
});

it('infers oauth when laravel/mcp oauth routes are registered', function () {
    Mcp::oauthRoutes();
    Mcp::web('/mcp/secure', SecureServer::class)->middleware('auth:api');

    expect(servers()->find('secure')->auth)->toBe(AuthScheme::OAuth);
});

it('makes the first server primary when none is flagged', function () {
    withConfig(['mcp-discovery.servers' => [ShopServer::class, HiddenServer::class]]);

    expect(servers()->primary()->key)->toBe('shop')
        ->and(servers()->find('hidden')->primary)->toBeFalse();
});

it('lets explicit config override attribute values', function () {
    withConfig(['mcp-discovery.servers' => [
        'articles' => [
            'class' => BlogServer::class,
            'name' => 'com.example/articles',
            'title' => 'Articles',
            'version' => '3.0.0',
            'public' => false,
            'protocol_versions' => ['2025-11-25'],
        ],
    ]]);

    expect(servers()->find('articles'))
        ->name->toBe('com.example/articles')
        ->title->toBe('Articles')
        ->version->toBe('3.0.0')
        ->public->toBeFalse()
        ->protocolVersions->toBe(['2025-11-25']);
});

it('throws when a configured server has no route and no url', function () {
    withConfig(['mcp-discovery.servers' => ['secure' => SecureServer::class]]);

    servers()->all();
})->throws(InvalidConfiguration::class);

it('accepts a url for servers that are not registered with Mcp::web()', function () {
    withConfig(['mcp-discovery.servers' => ['remote' => ['class' => HiddenServer::class, 'url' => 'https://mcp.example.com/']]]);

    expect(servers()->find('remote')->url)->toBe('https://mcp.example.com/');
});

it('builds urls for domain routes', function () {
    Route::domain('mcp.example.com')->group(fn () => Mcp::web('/secure', SecureServer::class));

    expect(servers()->find('secure')->url)->toBe('https://mcp.example.com/secure');
});

it('falls back to scanning the router when routes are cached', function () {
    // With cached routes, laravel/mcp never loads routes/ai.php and the registrar stays empty.
    app()->instance(Registrar::class, new Registrar);

    foreach (Route::getRoutes()->getRoutes() as $route) {
        $route->prepareForSerialization();
    }

    app()->forgetInstance(ServerRepository::class);

    expect(servers()->all()->keys()->all())->toBe(['blog', 'shop', 'ops'])
        ->and(servers()->find('blog')->url)->toBe('https://example.com/mcp/blog');
});
