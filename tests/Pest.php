<?php

use PieterVanLeuven\McpDiscovery\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * Reboot the app with extra config, so boot-time routes and middleware see it.
 *
 * @param  array<string, mixed>  $config
 */
function withConfig(array $config): void
{
    TestCase::$config = $config;

    test()->refreshApplication();
}

const CLAUDE_UA = 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)';

const BROWSER_UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15';
