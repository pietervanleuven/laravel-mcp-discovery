<?php

namespace PieterVanLeuven\McpDiscovery\Support;

use Symfony\Component\HttpFoundation\Response;

final class LinkHeader
{
    /**
     * Append Link values to a response, skipping ones it already carries.
     *
     * @param  list<string>  $links
     */
    public static function append(Response $response, array $links): void
    {
        $existing = $response->headers->all('Link');

        foreach ($links as $link) {
            if (! in_array($link, $existing, true)) {
                $response->headers->set('Link', $link, false);
                $existing[] = $link;
            }
        }
    }
}
