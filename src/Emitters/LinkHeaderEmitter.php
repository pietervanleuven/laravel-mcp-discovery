<?php

namespace PieterVanLeuven\McpDiscovery\Emitters;

use Illuminate\Http\Request;
use PieterVanLeuven\McpDiscovery\Emitters\Contracts\ProvidesLinks;
use Symfony\Component\HttpFoundation\Response;

/**
 * `Link: <https://example.com/mcp/blog>; rel="mcp"` on HTML responses.
 *
 * rel="mcp" is not registered with IANA yet. The server card is linked with
 * rel="service-desc" (RFC 8631).
 */
class LinkHeaderEmitter extends Emitter implements ProvidesLinks
{
    public function links(Request $request, Response $response): array
    {
        if (! $this->isHtml($response)) {
            return [];
        }

        if ($this->option('only', 'bots') === 'bots' && ! $this->discovery->isBot($request)) {
            return [];
        }

        return $this->discovery->linkValues((string) $this->option('rel', 'mcp'));
    }

    protected function isHtml(Response $response): bool
    {
        $type = (string) $response->headers->get('Content-Type', '');

        return $type === '' || str_contains($type, 'text/html');
    }
}
