<?php

namespace PieterVanLeuven\McpDiscovery\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use PieterVanLeuven\McpDiscovery\McpDiscovery;
use PieterVanLeuven\McpDiscovery\Support\LinkHeader;
use Symfony\Component\HttpFoundation\Response;

class AddDiscoveryHeaders
{
    public function __construct(protected McpDiscovery $discovery) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        LinkHeader::append($response, $this->discovery->links($request, $response));

        return $response;
    }
}
