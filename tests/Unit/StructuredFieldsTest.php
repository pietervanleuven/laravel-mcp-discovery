<?php

use PieterVanLeuven\McpDiscovery\Support\StructuredFields;

it('splits dictionaries without breaking on commas in strings or lists', function () {
    expect(StructuredFields::dictionary('a=("x" "y, z");p=1, b=:YWJj:, c'))->toBe([
        'a' => '("x" "y, z");p=1',
        'b' => ':YWJj:',
        'c' => '?1',
    ]);
});

it('parses inner lists with parameters', function () {
    expect(StructuredFields::innerList('("@authority" "signature-agent";key="sig1");created=1700000000;keyid="abc";tag="web-bot-auth"'))->toBe([
        'items' => ['"@authority"', '"signature-agent";key="sig1"'],
        'params' => ['created' => 1700000000, 'keyid' => 'abc', 'tag' => 'web-bot-auth'],
    ]);
});

it('parses bare items', function (string $raw, mixed $expected) {
    expect(StructuredFields::bareItem($raw))->toBe($expected);
})->with([
    ['"hi"', 'hi'],
    [':YWJj:', 'YWJj'],
    ['42', 42],
    ['?1', true],
    ['?0', false],
    ['token', 'token'],
]);
