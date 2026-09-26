<?php

namespace PieterVanLeuven\McpDiscovery\Bots;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Request;
use PieterVanLeuven\McpDiscovery\Enums\SignatureStatus;
use PieterVanLeuven\McpDiscovery\Support\StructuredFields;
use Throwable;

/**
 * Verifies Web Bot Auth signatures: RFC 9421 HTTP Message Signatures with
 * Ed25519 keys published in the agent's key directory.
 *
 * @see https://datatracker.ietf.org/doc/draft-meunier-web-bot-auth-architecture/
 */
class WebBotAuthVerifier
{
    public const DIRECTORY_PATH = '/.well-known/http-message-signatures-directory';

    public const ATTRIBUTE = 'mcp-discovery.signature';

    protected const CLOCK_SKEW = 60;

    public function __construct(
        protected Http $http,
        protected Cache $cache,
    ) {}

    public function verify(Request $request): SignatureStatus
    {
        $cached = $request->attributes->get(self::ATTRIBUTE);

        if ($cached instanceof SignatureStatus) {
            return $cached;
        }

        $status = $this->check($request);
        $request->attributes->set(self::ATTRIBUTE, $status);

        return $status;
    }

    public function isVerified(Request $request): bool
    {
        return $this->verify($request) === SignatureStatus::Valid;
    }

    protected function check(Request $request): SignatureStatus
    {
        $inputHeader = $request->headers->get('Signature-Input');
        $signatureHeader = $request->headers->get('Signature');

        if ($inputHeader === null || $signatureHeader === null) {
            return SignatureStatus::Unsigned;
        }

        try {
            return $this->checkSignature($request, $inputHeader, $signatureHeader)
                ? SignatureStatus::Valid
                : SignatureStatus::Invalid;
        } catch (Throwable) {
            return SignatureStatus::Invalid;
        }
    }

    protected function checkSignature(Request $request, string $inputHeader, string $signatureHeader): bool
    {
        $inputs = StructuredFields::dictionary($inputHeader);
        $signatures = StructuredFields::dictionary($signatureHeader);

        $label = $this->pickLabel($inputs);

        if ($label === null || ! isset($signatures[$label])) {
            return false;
        }

        $rawParams = $inputs[$label];
        $parsed = StructuredFields::innerList($rawParams);

        if ($parsed === null) {
            return false;
        }

        $params = $parsed['params'];

        if (! $this->withinValidityWindow($params)) {
            return false;
        }

        if (isset($params['alg']) && $params['alg'] !== 'ed25519') {
            return false;
        }

        $agent = $this->signatureAgent($request, $label);

        if ($agent === null || ! isset($params['keyid']) || ! is_string($params['keyid'])) {
            return false;
        }

        $publicKey = $this->publicKey($agent, $params['keyid']);

        if ($publicKey === null) {
            return false;
        }

        $signature = base64_decode((string) StructuredFields::bareItem($signatures[$label]), true);

        if ($signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        $base = $this->signatureBase($request, $parsed['items'], $rawParams);

        if ($base === null) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($signature, $base, $publicKey);
    }

    /**
     * @param  array<string, string>  $inputs
     */
    protected function pickLabel(array $inputs): ?string
    {
        foreach ($inputs as $label => $raw) {
            $parsed = StructuredFields::innerList($raw);

            if (($parsed['params']['tag'] ?? null) === 'web-bot-auth') {
                return $label;
            }
        }

        return array_key_first($inputs);
    }

    /**
     * @param  array<string, string|int|bool>  $params
     */
    protected function withinValidityWindow(array $params): bool
    {
        $now = time();
        $created = isset($params['created']) ? (int) $params['created'] : null;
        $expires = isset($params['expires']) ? (int) $params['expires'] : null;

        if ($created !== null && $created > $now + self::CLOCK_SKEW) {
            return false;
        }

        if ($expires !== null) {
            return $expires >= $now - self::CLOCK_SKEW;
        }

        if ($created === null) {
            return false;
        }

        return $created + (int) config('mcp-discovery.bot_gate.web_bot_auth.max_age', 300) >= $now;
    }

    /**
     * Signature-Agent is either an sf-string or, in newer drafts, a dictionary keyed by signature label.
     */
    protected function signatureAgent(Request $request, string $label): ?string
    {
        $header = trim((string) $request->headers->get('Signature-Agent', ''));

        if ($header === '') {
            return null;
        }

        if (! str_starts_with($header, '"')) {
            $header = StructuredFields::dictionary($header)[$label] ?? '';
        }

        $agent = StructuredFields::bareItem($header);

        if (! is_string($agent) || parse_url($agent, PHP_URL_SCHEME) !== 'https') {
            return null;
        }

        return rtrim($agent, '/');
    }

    protected function publicKey(string $agent, string $keyId): ?string
    {
        foreach ($this->directory($agent) as $jwk) {
            if (($jwk['kty'] ?? null) !== 'OKP' || ($jwk['crv'] ?? null) !== 'Ed25519' || ! isset($jwk['x'])) {
                continue;
            }

            if (! hash_equals(self::thumbprint($jwk), $keyId)) {
                continue;
            }

            $key = self::base64UrlDecode((string) $jwk['x']);

            return strlen($key) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES ? $key : null;
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function directory(string $agent): array
    {
        $url = $agent.self::DIRECTORY_PATH;

        return $this->cache->remember(
            'mcp-discovery:web-bot-auth:'.sha1($url),
            (int) config('mcp-discovery.bot_gate.web_bot_auth.directory_cache', 3600),
            function () use ($url): array {
                $response = $this->http->timeout(3)->acceptJson()->get($url);

                $keys = $response->successful() ? $response->json('keys') : [];

                return is_array($keys) ? array_values(array_filter($keys, 'is_array')) : [];
            },
        );
    }

    /**
     * RFC 9421 section 2.5.
     *
     * @param  list<string>  $components
     */
    protected function signatureBase(Request $request, array $components, string $rawParams): ?string
    {
        $lines = [];

        foreach ($components as $component) {
            $value = $this->componentValue($request, $component);

            if ($value === null) {
                return null;
            }

            $lines[] = "{$component}: {$value}";
        }

        $lines[] = "\"@signature-params\": {$rawParams}";

        return implode("\n", $lines);
    }

    protected function componentValue(Request $request, string $component): ?string
    {
        $semicolon = strpos($component, ';');
        $name = StructuredFields::bareItem($semicolon === false ? $component : substr($component, 0, $semicolon));
        $params = $semicolon === false ? [] : StructuredFields::parameters(substr($component, $semicolon + 1));

        if (! is_string($name)) {
            return null;
        }

        $name = strtolower($name);

        if (str_starts_with($name, '@')) {
            return $this->derivedComponent($request, $name);
        }

        if (! $request->headers->has($name)) {
            return null;
        }

        $value = implode(', ', array_map('trim', $request->headers->all($name)));

        if (isset($params['key']) && is_string($params['key'])) {
            return StructuredFields::dictionary($value)[$params['key']] ?? null;
        }

        return $value;
    }

    protected function derivedComponent(Request $request, string $name): ?string
    {
        $query = $request->getQueryString();

        return match ($name) {
            '@method' => strtoupper($request->getMethod()),
            '@authority' => strtolower($request->getHttpHost()),
            '@scheme' => strtolower($request->getScheme()),
            '@target-uri' => $request->getUri(),
            '@request-target' => $request->getRequestUri(),
            '@path' => $request->getPathInfo(),
            '@query' => '?'.($query ?? ''),
            default => null,
        };
    }

    /**
     * RFC 7638 JWK thumbprint for an OKP key, base64url-encoded.
     *
     * @param  array<string, mixed>  $jwk
     */
    public static function thumbprint(array $jwk): string
    {
        $canonical = json_encode([
            'crv' => $jwk['crv'],
            'kty' => $jwk['kty'],
            'x' => $jwk['x'],
        ], JSON_UNESCAPED_SLASHES);

        return self::base64UrlEncode(hash('sha256', (string) $canonical, true));
    }

    public static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}
