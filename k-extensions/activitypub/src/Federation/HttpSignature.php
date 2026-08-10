<?php

declare(strict_types=1);

namespace Kopling\Activitypub\Federation;

/**
 * draft-cavage HTTP Signatures -- what Mastodon and the rest of the deployed fediverse actually
 * check today (not RFC 9421); see the federation plan's Phase 3. `sign()`/`verify()` share the
 * exact same signing-string construction on purpose: a verifier that built it differently than
 * the signer would reject every genuine request.
 */
class HttpSignature
{
    protected const ALGORITHM = 'rsa-sha256';

    protected const SIGNED_HEADERS = ['(request-target)', 'host', 'date', 'digest'];

    /**
     * @return array<string, string> headers to add to the outbound request
     */
    public static function sign(string $privateKeyPem, string $keyId, string $method, string $url, string $body): array
    {
        $url = parse_url($url);
        $host = $url['host'].(isset($url['port']) ? ':'.$url['port'] : '');
        $path = ($url['path'] ?? '/').(isset($url['query']) ? '?'.$url['query'] : '');
        $date = gmdate('D, d M Y H:i:s \G\M\T');
        $digest = 'SHA-256='.base64_encode(hash('sha256', $body, true));

        $signingString = static::signingString(strtolower($method), $path, [
            'host' => $host,
            'date' => $date,
            'digest' => $digest,
        ]);

        openssl_sign($signingString, $signature, $privateKeyPem, OPENSSL_ALGO_SHA256);

        return [
            'Host' => $host,
            'Date' => $date,
            'Digest' => $digest,
            'Signature' => sprintf(
                'keyId="%s",algorithm="%s",headers="%s",signature="%s"',
                $keyId,
                static::ALGORITHM,
                implode(' ', static::SIGNED_HEADERS),
                base64_encode($signature),
            ),
        ];
    }

    /**
     * @param  array<string, string>  $headers  every request header, lowercase-keyed
     */
    /**
     * The `keyId` a `Signature` header claims, before any verification happens -- callers use
     * this to resolve which actor's public key to verify against (see
     * `Federation\Manager::resolveActor()`), never to trust the request on its own; the actual
     * `verify()` call still has to pass against that resolved key.
     */
    public static function keyId(string $signatureHeader): ?string
    {
        return static::parseSignatureHeader($signatureHeader)['keyId'] ?? null;
    }

    public static function verify(string $publicKeyPem, string $method, string $path, array $headers, string $body): bool
    {
        $signatureHeader = $headers['signature'] ?? null;

        if ($signatureHeader === null) {
            return false;
        }

        $parsed = static::parseSignatureHeader($signatureHeader);

        if ($parsed === null || ! isset($parsed['signature'], $parsed['headers'])) {
            return false;
        }

        // Digest is verified against the actual body, not merely trusted because it's present --
        // a tampered body with an untouched Digest header would otherwise still pass.
        if (isset($headers['digest']) && $headers['digest'] !== 'SHA-256='.base64_encode(hash('sha256', $body, true))) {
            return false;
        }

        $signedHeaderNames = explode(' ', $parsed['headers']);
        $values = [];

        foreach ($signedHeaderNames as $name) {
            if ($name === '(request-target)') {
                $values[$name] = strtolower($method).' '.$path;

                continue;
            }

            if (! isset($headers[$name])) {
                return false;
            }

            $values[$name] = $headers[$name];
        }

        $signingString = implode("\n", array_map(
            fn ($name) => "$name: {$values[$name]}",
            $signedHeaderNames,
        ));

        return openssl_verify($signingString, base64_decode($parsed['signature']), $publicKeyPem, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected static function signingString(string $method, string $path, array $headers): string
    {
        $values = array_merge(['(request-target)' => "$method $path"], $headers);

        return implode("\n", array_map(fn ($name) => "$name: {$values[$name]}", array_keys($values)));
    }

    /**
     * @return array<string, string>|null
     */
    protected static function parseSignatureHeader(string $header): ?array
    {
        if (! preg_match_all('/(\w+)="([^"]*)"/', $header, $matches, PREG_SET_ORDER)) {
            return null;
        }

        $parsed = [];

        foreach ($matches as $match) {
            $parsed[$match[1]] = $match[2];
        }

        return $parsed;
    }
}
