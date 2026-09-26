<?php

namespace PieterVanLeuven\McpDiscovery\Emitters;

use Illuminate\Http\Request;
use PieterVanLeuven\McpDiscovery\Emitters\Contracts\ProvidesLinks;
use Symfony\Component\HttpFoundation\Response;

/**
 * Clients asking for `Accept: text/markdown` are almost always agents, so they
 * always get the MCP Link header, whatever the response type.
 *
 * @see https://developers.cloudflare.com/fundamentals/reference/markdown-for-agents
 */
class MarkdownHintEmitter extends Emitter implements ProvidesLinks
{
    public function links(Request $request, Response $response): array
    {
        if (! str_contains(strtolower((string) $request->header('Accept', '')), 'text/markdown')) {
            return [];
        }

        $response->headers->set('Vary', trim($response->headers->get('Vary', '').', Accept', ', '));

        return $this->discovery->linkValues(
            (string) $this->discovery->emitterOption('link_header', 'rel', 'mcp'),
        );
    }
}
