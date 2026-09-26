# Changelog

All notable changes to `laravel-mcp-discovery` will be documented in this file.

## Unreleased

- Server discovery from `Mcp::web()` routes, with metadata from laravel/mcp attributes, `#[Discoverable]` and config
- Server Card emitter (`/.well-known/mcp-server-card[/{key}]`, optional index)
- `llms.txt` and `robots.txt` emitters (dynamic route or `mcp-discovery:write`)
- `Link: rel="mcp"` header and `Accept: text/markdown` hint
- A2A Agent Card emitter
- Bot gate (redirect-instead-of-reject) with pluggable detector
- Web Bot Auth (RFC 9421, Ed25519) signature verification
- Artisan: `mcp-discovery:list`, `mcp-discovery:validate`, `mcp-discovery:write`, `mcp-discovery:publish-registry`
