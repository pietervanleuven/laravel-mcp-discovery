<?php

namespace PieterVanLeuven\McpDiscovery\Emitters;

use PieterVanLeuven\McpDiscovery\ServerDescriptor;

/**
 * @see https://llmstxt.org/
 */
class LlmsTxtEmitter extends TextFileEmitter
{
    public function fileName(): string
    {
        return 'llms.txt';
    }

    public function section(): string
    {
        $servers = $this->discovery->servers()->visible();

        if ($servers->isEmpty()) {
            return '';
        }

        $lines = [
            '## MCP servers',
            '',
            'This site offers its content over the Model Context Protocol (MCP). Agents should connect to these servers instead of scraping HTML.',
            '',
        ];

        foreach ($servers as $server) {
            $lines[] = $this->line($server);
        }

        return implode("\n", $lines);
    }

    protected function line(ServerDescriptor $server): string
    {
        $details = array_filter([
            $server->description !== null ? rtrim($server->description, '. ').'.' : null,
            'Streamable HTTP endpoint.',
            $this->discovery->emitterEnabled('server_card') ? 'Server card: '.$this->discovery->cardUrl($server) : null,
        ]);

        return sprintf('- [%s](%s): %s', $server->title ?? $server->name, $server->url, implode(' ', $details));
    }

    protected function defaultBase(): string
    {
        $name = (string) config('app.name', 'Laravel');

        return "# {$name}\n\n> {$name} exposes structured tools for AI agents over MCP.\n";
    }

    protected function startMarker(): string
    {
        return '<!-- mcp-discovery:start -->';
    }

    protected function endMarker(): string
    {
        return '<!-- mcp-discovery:end -->';
    }
}
