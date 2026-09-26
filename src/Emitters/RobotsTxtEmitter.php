<?php

namespace PieterVanLeuven\McpDiscovery\Emitters;

/**
 * Adds MCP pointers (as comments, since robots.txt has no directive for them)
 * and Content Signals.
 *
 * @see https://contentsignals.org/
 */
class RobotsTxtEmitter extends TextFileEmitter
{
    public function fileName(): string
    {
        return 'robots.txt';
    }

    public function mode(): string
    {
        return (string) $this->option('mode', 'file');
    }

    public function section(): string
    {
        $lines = [];

        foreach ($this->discovery->servers()->visible() as $server) {
            $lines[] = "# MCP server ({$server->name}): {$server->url}";

            if ($this->discovery->emitterEnabled('server_card')) {
                $lines[] = '# MCP server card: '.$this->discovery->cardUrl($server);
            }
        }

        $signals = collect((array) $this->option('content_signals', []))
            ->map(fn ($value, $signal) => "{$signal}=".(is_bool($value) ? ($value ? 'yes' : 'no') : $value))
            ->implode(', ');

        if ($signals !== '') {
            // Crawlers merge groups for the same user agent, so this adds no rules of its own.
            $lines[] = 'User-agent: *';
            $lines[] = "Content-Signal: {$signals}";
        }

        return implode("\n", $lines);
    }

    protected function defaultBase(): string
    {
        return "User-agent: *\nDisallow:\n";
    }

    protected function startMarker(): string
    {
        return '# mcp-discovery:start';
    }

    protected function endMarker(): string
    {
        return '# mcp-discovery:end';
    }
}
