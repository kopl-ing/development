<?php

declare(strict_types=1);

namespace Kopling\Mcp\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Kopling\Mcp\Access;
use Kopling\Mcp\Registry;
use Laravel\Mcp\Server\Http\Controllers\OAuthRegisterController;

/**
 * Laravel MCP's dynamic client registration, answering with Kopling's scopes instead of its fixed `mcp:use`.
 */
class RegisterClientController extends OAuthRegisterController
{
    public function __invoke(Request $request): JsonResponse
    {
        $response = parent::__invoke($request);

        if ($response->getStatusCode() === 201) {
            $response->setData([
                ...$response->getData(true),
                'scope' => implode(' ', [Access::SCOPE, ...array_keys(app(Registry::class)->scopes())]),
            ]);
        }

        return $response;
    }
}
