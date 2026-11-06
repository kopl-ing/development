<?php

declare(strict_types=1);

namespace Kopling\Mcp\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Kopling\Mcp\Access;
use Kopling\Mcp\Registry;

/**
 * OAuth discovery for MCP clients, pointing at `kopling/oauth`'s routes (Laravel MCP's own assumes Passport's default route names).
 */
class DiscoveryController
{
    public function protectedResource(?string $path = null): JsonResponse
    {
        return response()->json([
            'resource' => url('/'.ltrim((string) $path, '/')),
            'authorization_servers' => [url('/')],
            'scopes_supported' => $this->scopes(),
            'bearer_methods_supported' => ['header'],
        ]);
    }

    public function authorizationServer(): JsonResponse
    {
        return response()->json(array_filter([
            'issuer' => url('/'),
            'authorization_endpoint' => route('kopling-oauth::oauth/authorize'),
            'token_endpoint' => route('kopling-oauth::token'),
            'registration_endpoint' => Route::has('kopling-mcp::register') ? route('kopling-mcp::register') : null,
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none', 'client_secret_basic', 'client_secret_post'],
            'scopes_supported' => $this->scopes(),
        ]));
    }

    /**
     * @return list<string>
     */
    private function scopes(): array
    {
        return [Access::SCOPE, ...array_keys(app(Registry::class)->scopes())];
    }
}
