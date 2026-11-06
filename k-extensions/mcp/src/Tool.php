<?php

declare(strict_types=1);

namespace Kopling\Mcp;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Kopling\OAuth\Person;
use Laravel\Mcp\Server\Tool as LaravelTool;

/**
 * Base for every Kopling MCP tool: prefixed name, scope and permission filtering, and the person behind the token.
 *
 * Declare a local name (`protected string $name = 'plan-match';`); `kopling/mcp` adds the extension prefix.
 */
abstract class Tool extends LaravelTool
{
    public function name(): string
    {
        return app(Registry::class)->tool(static::class)['name'] ?? $this->localName();
    }

    public function localName(): string
    {
        return parent::name();
    }

    public function shouldRegister(): bool
    {
        $person = $this->person();

        return Access::allows($person, app(Registry::class)->tool(static::class)['scope'] ?? null) && $this->allows($person);
    }

    /**
     * Extra condition on top of `use-mcp` and the scope, e.g. one of the extension's own permissions.
     */
    protected function allows(Person $person): bool
    {
        return true;
    }

    protected function person(): ?Person
    {
        $person = Auth::guard('api')->user();

        return $person instanceof Person ? $person : null;
    }

    protected function authorize(string $ability, mixed ...$arguments): void
    {
        Gate::forUser($this->person())->authorize($ability, $arguments);
    }

    /**
     * Records which team a call was about in the audit log.
     */
    protected function aboutTeam(?string $teamId): void
    {
        app(Audit::class)->teamId = $teamId;
    }
}
