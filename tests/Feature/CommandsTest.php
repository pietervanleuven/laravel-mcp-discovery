<?php

use Illuminate\Support\Facades\File;
use PieterVanLeuven\McpDiscovery\Console\PublishRegistryCommand;
use PieterVanLeuven\McpDiscovery\Tests\Fixtures\Servers\BlogServer;

afterEach(function () {
    File::delete([public_path('llms.txt'), public_path('robots.txt'), base_path('server.json')]);
});

it('lists servers and emitters', function () {
    $this->artisan('mcp-discovery:list')
        ->expectsOutputToContain('com.example/blog')
        ->expectsOutputToContain('https://example.com/mcp/shop')
        ->expectsOutputToContain('server_card')
        ->assertSuccessful();
});

it('lists servers as json', function () {
    $this->artisan('mcp-discovery:list --json')
        ->expectsOutputToContain('"name": "com.example/blog"')
        ->assertSuccessful();
});

it('validates a correct setup', function () {
    $this->artisan('mcp-discovery:validate')->assertSuccessful();
});

it('fails validation for a bad registry name', function () {
    withConfig(['mcp-discovery.servers' => ['blog' => ['class' => BlogServer::class, 'name' => 'not a name']]]);

    $this->artisan('mcp-discovery:validate')
        ->expectsOutputToContain('must be reverse-DNS')
        ->assertFailed();
});

it('fails validation for duplicate names and primaries', function () {
    withConfig(['mcp-discovery.servers' => [
        'a' => ['class' => BlogServer::class, 'primary' => true],
        'b' => ['class' => BlogServer::class, 'primary' => true],
    ]]);

    $this->artisan('mcp-discovery:validate')
        ->expectsOutputToContain('More than one server uses the name')
        ->expectsOutputToContain('More than one server is marked as primary')
        ->assertFailed();
});

it('warns when a static llms.txt shadows the route', function () {
    File::put(public_path('llms.txt'), "# Static\n");

    $this->artisan('mcp-discovery:validate')
        ->expectsOutputToContain('public/llms.txt exists')
        ->assertSuccessful();
});

it('writes sections into public files in file mode', function () {
    withConfig([
        'mcp-discovery.emitters.llms_txt.mode' => 'file',
        'mcp-discovery.emitters.robots_txt.enabled' => true,
    ]);

    File::put(public_path('robots.txt'), "User-agent: *\nDisallow:\n");

    $this->artisan('mcp-discovery:write')->assertSuccessful();

    expect(File::get(public_path('llms.txt')))->toContain('## MCP servers')
        ->and(File::get(public_path('robots.txt')))->toStartWith("User-agent: *\nDisallow:\n")->toContain('Content-Signal:');

    $this->artisan('mcp-discovery:write')->expectsOutputToContain('unchanged')->assertSuccessful();
    $this->artisan('mcp-discovery:validate')->assertSuccessful();
});

it('does not write anything on a dry run', function () {
    withConfig(['mcp-discovery.emitters.llms_txt.mode' => 'file']);

    $this->artisan('mcp-discovery:write --dry-run')->expectsOutputToContain('## MCP servers')->assertSuccessful();

    expect(File::exists(public_path('llms.txt')))->toBeFalse();
});

it('generates a server.json for the MCP Registry', function () {
    $this->artisan('mcp-discovery:publish-registry shop')
        ->expectsOutputToContain('mcp-publisher login dns --domain example.com')
        ->assertSuccessful();

    expect(json_decode(File::get(base_path('server.json')), true))->toBe([
        '$schema' => PublishRegistryCommand::SCHEMA,
        'name' => 'com.example/shop',
        'title' => 'Shop',
        'description' => 'Use the shop tools to browse the catalogue.',
        'version' => '2.1.0',
        'websiteUrl' => 'https://example.com',
        'remotes' => [[
            'type' => 'streamable-http',
            'url' => 'https://example.com/mcp/shop',
            'headers' => [[
                'name' => 'Authorization',
                'description' => 'Bearer token, sent as "Bearer <token>".',
                'isRequired' => true,
                'isSecret' => true,
            ]],
        ]],
    ]);
});

it('fails to generate a server.json for an unknown server', function () {
    $this->artisan('mcp-discovery:publish-registry nope')->assertFailed();
});
