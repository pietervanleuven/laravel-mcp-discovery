<?php

namespace PieterVanLeuven\McpDiscovery\Support;

final class Url
{
    /**
     * Absolute URL built from APP_URL rather than the request's Host header,
     * so publicly cached discovery documents can't be poisoned.
     */
    public static function to(string $path = ''): string
    {
        $root = rtrim((string) config('app.url'), '/');
        $path = ltrim($path, '/');

        if ($root === '') {
            return url($path);
        }

        return $path === '' ? $root : "{$root}/{$path}";
    }
}
