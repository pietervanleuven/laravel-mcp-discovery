<?php

namespace PieterVanLeuven\McpDiscovery\Emitters\Contracts;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

interface ProvidesLinks
{
    /**
     * Link header values to add to the response, e.g. `<https://example.com/mcp>; rel="mcp"`.
     *
     * @return list<string>
     */
    public function links(Request $request, Response $response): array;
}
