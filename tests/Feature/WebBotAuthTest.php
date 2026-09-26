<?php

use Illuminate\Support\Facades\Http;
use PieterVanLeuven\McpDiscovery\Bots\WebBotAuthVerifier;

const AGENT = 'https://agent.example';

beforeEach(function () {
    withConfig([
        'mcp-discovery.bot_gate.enabled' => true,
        'mcp-discovery.bot_gate.web_bot_auth.enabled' => true,
    ]);

    $this->keypair = sodium_crypto_sign_keypair();
    $this->jwk = [
        'kty' => 'OKP',
        'crv' => 'Ed25519',
        'x' => WebBotAuthVerifier::base64UrlEncode(sodium_crypto_sign_publickey($this->keypair)),
    ];

    Http::fake([
        AGENT.WebBotAuthVerifier::DIRECTORY_PATH => Http::response(['keys' => [$this->jwk]]),
    ]);
});

/**
 * @return array<string, string>
 */
function signedHeaders(string $secretKey, array $jwk, array $overrides = []): array
{
    $created = $overrides['created'] ?? time();
    $params = sprintf(
        '("@authority" "signature-agent");created=%d;expires=%d;keyid="%s";alg="ed25519";tag="web-bot-auth"',
        $created,
        $created + 60,
        $overrides['keyid'] ?? WebBotAuthVerifier::thumbprint($jwk),
    );

    $agentHeader = '"'.AGENT.'"';
    $base = implode("\n", [
        '"@authority": '.($overrides['authority'] ?? 'example.com'),
        '"signature-agent": '.$agentHeader,
        '"@signature-params": '.$params,
    ]);

    return [
        'User-Agent' => CLAUDE_UA,
        'Signature-Agent' => $agentHeader,
        'Signature-Input' => "sig1={$params}",
        'Signature' => 'sig1=:'.base64_encode(sodium_crypto_sign_detached($base, $secretKey)).':',
    ];
}

it('lets verified agents through with the Link header', function () {
    $headers = signedHeaders(sodium_crypto_sign_secretkey($this->keypair), $this->jwk);

    $response = $this->get('https://example.com/articles/hello', $headers)->assertOk()->assertSee('Hello');

    expect($response->headers->all('Link'))->toContain('<https://example.com/mcp/blog>; rel="mcp"; title="Blog"');
});

it('gates requests with a bad signature', function () {
    $other = sodium_crypto_sign_keypair();
    $headers = signedHeaders(sodium_crypto_sign_secretkey($other), $this->jwk);

    $this->get('https://example.com/articles/hello', ['User-Agent' => BROWSER_UA] + $headers)->assertForbidden();
});

it('gates signatures made for another host', function () {
    $headers = signedHeaders(sodium_crypto_sign_secretkey($this->keypair), $this->jwk, ['authority' => 'other.test']);

    $this->get('https://example.com/articles/hello', $headers)->assertForbidden();
});

it('gates expired signatures', function () {
    $headers = signedHeaders(sodium_crypto_sign_secretkey($this->keypair), $this->jwk, ['created' => time() - 3600]);

    $this->get('https://example.com/articles/hello', $headers)->assertForbidden();
});

it('gates unknown key ids', function () {
    $headers = signedHeaders(sodium_crypto_sign_secretkey($this->keypair), $this->jwk, ['keyid' => 'unknown']);

    $this->get('https://example.com/articles/hello', $headers)->assertForbidden();
});

it('gates unsigned bots by default', function () {
    $this->get('https://example.com/articles/hello', ['User-Agent' => CLAUDE_UA])->assertForbidden();
});

it('can let unsigned bots through', function () {
    withConfig([
        'mcp-discovery.bot_gate.enabled' => true,
        'mcp-discovery.bot_gate.web_bot_auth.enabled' => true,
        'mcp-discovery.bot_gate.web_bot_auth.unsigned' => 'pass',
    ]);

    $this->get('https://example.com/articles/hello', ['User-Agent' => CLAUDE_UA])->assertOk();
});

it('shows non-public server cards to verified agents', function () {
    $headers = signedHeaders(sodium_crypto_sign_secretkey($this->keypair), $this->jwk);

    $this->getJson('https://example.com/.well-known/mcp-server-card/ops', $headers)
        ->assertOk()
        ->assertJsonPath('name', 'com.example/ops');
});

it('caches the key directory', function () {
    $secret = sodium_crypto_sign_secretkey($this->keypair);

    $this->get('https://example.com/articles/hello', signedHeaders($secret, $this->jwk))->assertOk();
    $this->get('https://example.com/articles/hello', signedHeaders($secret, $this->jwk))->assertOk();

    Http::assertSentCount(1);
});

it('accepts the dictionary form of Signature-Agent', function () {
    $secret = sodium_crypto_sign_secretkey($this->keypair);
    $created = time();
    $params = sprintf('("@authority" "signature-agent";key="sig1");created=%d;keyid="%s";tag="web-bot-auth"', $created, WebBotAuthVerifier::thumbprint($this->jwk));
    $base = "\"@authority\": example.com\n\"signature-agent\";key=\"sig1\": \"".AGENT."\"\n\"@signature-params\": {$params}";

    $this->get('https://example.com/articles/hello', [
        'User-Agent' => CLAUDE_UA,
        'Signature-Agent' => 'sig1="'.AGENT.'"',
        'Signature-Input' => "sig1={$params}",
        'Signature' => 'sig1=:'.base64_encode(sodium_crypto_sign_detached($base, $secret)).':',
    ])->assertOk();
});
