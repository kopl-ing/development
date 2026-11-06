<?php

declare(strict_types=1);

namespace Kopling\Mcp\Methods;

use Generator;
use Illuminate\Support\Facades\Auth;
use Kopling\Mcp\Audit;
use Kopling\Mcp\Registry;
use Kopling\Mcp\ToolCall;
use Kopling\OAuth\Person;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Methods\CallTool as LaravelCallTool;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

/**
 * Laravel MCP's tool call, written to the audit log whatever the outcome.
 */
class CallTool extends LaravelCallTool
{
    public function handle(JsonRpcRequest $request, ServerContext $context): Generator|JsonRpcResponse
    {
        $name = (string) $request->get('name');
        $arguments = (array) ($request->params['arguments'] ?? []);

        try {
            $response = parent::handle($request, $context);
        } catch (JsonRpcException $e) {
            $this->record($name, $arguments, app(Registry::class)->known($name) ? ToolCall::DENIED : ToolCall::ERROR);

            throw $e;
        }

        $failed = $response instanceof JsonRpcResponse && ($response->content['result']['isError'] ?? false);
        $this->record($name, $arguments, $failed ? ToolCall::ERROR : ToolCall::OK);

        return $response;
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function record(string $name, array $arguments, string $outcome): void
    {
        $person = Auth::guard('api')->user();
        $person = $person instanceof Person ? $person : null;

        app(Audit::class)->record($person, $person?->token()?->oauth_client_id, $name, $arguments, $outcome);
    }
}
