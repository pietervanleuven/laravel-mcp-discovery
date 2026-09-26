<?php

namespace PieterVanLeuven\McpDiscovery\Support;

/**
 * The small subset of RFC 8941 structured field parsing that Web Bot Auth needs.
 * Values are returned raw (exactly as sent), because RFC 9421 signs the raw
 * serialisation of the signature parameters.
 */
final class StructuredFields
{
    /**
     * Split a dictionary into raw member values keyed by member name.
     *
     * @return array<string, string>
     */
    public static function dictionary(string $header): array
    {
        $members = [];

        foreach (self::splitTopLevel($header, ',') as $member) {
            $member = trim($member);

            if ($member === '') {
                continue;
            }

            $equals = strpos($member, '=');

            if ($equals === false) {
                $members[$member] = '?1';

                continue;
            }

            $members[trim(substr($member, 0, $equals))] = trim(substr($member, $equals + 1));
        }

        return $members;
    }

    /**
     * Parse an inner list with parameters: `("@authority" "x";key="a");created=1;keyid="k"`.
     *
     * @return array{items: list<string>, params: array<string, string|int|bool>}|null
     */
    public static function innerList(string $value): ?array
    {
        $value = trim($value);

        if (! str_starts_with($value, '(')) {
            return null;
        }

        $close = self::closingParenthesis($value);

        if ($close === null) {
            return null;
        }

        $items = array_values(array_filter(
            array_map('trim', self::splitTopLevel(substr($value, 1, $close - 1), ' ')),
            fn (string $item) => $item !== '',
        ));

        return ['items' => $items, 'params' => self::parameters(substr($value, $close + 1))];
    }

    /**
     * @return array<string, string|int|bool>
     */
    public static function parameters(string $value): array
    {
        $params = [];

        foreach (self::splitTopLevel($value, ';') as $param) {
            $param = trim($param);

            if ($param === '') {
                continue;
            }

            [$name, $raw] = array_pad(explode('=', $param, 2), 2, null);
            $params[trim($name)] = $raw === null ? true : self::bareItem(trim($raw));
        }

        return $params;
    }

    public static function bareItem(string $raw): string|int|bool
    {
        if (str_starts_with($raw, '"') && str_ends_with($raw, '"') && strlen($raw) >= 2) {
            return stripcslashes(substr($raw, 1, -1));
        }

        if (str_starts_with($raw, ':') && str_ends_with($raw, ':') && strlen($raw) >= 2) {
            return substr($raw, 1, -1);
        }

        if ($raw === '?1' || $raw === '?0') {
            return $raw === '?1';
        }

        if (preg_match('/^-?\d+$/', $raw)) {
            return (int) $raw;
        }

        return $raw;
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevel(string $value, string $separator): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $quoted = false;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];

            if ($quoted) {
                $current .= $char;

                if ($char === '\\' && $i + 1 < $length) {
                    $current .= $value[++$i];
                } elseif ($char === '"') {
                    $quoted = false;
                }

                continue;
            }

            if ($char === '"') {
                $quoted = true;
            } elseif ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            } elseif ($char === $separator && $depth === 0) {
                $parts[] = $current;
                $current = '';

                continue;
            }

            $current .= $char;
        }

        $parts[] = $current;

        return $parts;
    }

    private static function closingParenthesis(string $value): ?int
    {
        $quoted = false;
        $length = strlen($value);

        for ($i = 1; $i < $length; $i++) {
            $char = $value[$i];

            if ($quoted) {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === '"') {
                    $quoted = false;
                }

                continue;
            }

            if ($char === '"') {
                $quoted = true;
            } elseif ($char === ')') {
                return $i;
            }
        }

        return null;
    }
}
