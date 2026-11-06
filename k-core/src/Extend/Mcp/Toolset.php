<?php

declare(strict_types=1);

namespace Kopling\Core\Extend\Mcp;

/**
 * A group of MCP tools an extension offers, by scope; protocol-free, read by `kopling/mcp` (same split as `Extend\Federation` and `activitypub`).
 *
 * Ids are local: `Manager` prefixes the toolset and scope ids with the extension id, and sets `$extension`.
 */
class Toolset
{
    public ?string $extension = null;

    public string $label = '';

    /** @var array<string, array{description: string, tools: list<class-string>}> */
    public array $scopes = [];

    public function __construct(public string $id)
    {
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @param list<class-string> $tools
     */
    public function scope(string $id, string $description, array $tools): static
    {
        $this->scopes[$id] = ['description' => $description, 'tools' => array_values($tools)];

        return $this;
    }

    /**
     * @return array<class-string, string> tool class => scope id
     */
    public function tools(): array
    {
        $tools = [];
        foreach ($this->scopes as $scope => $declared) {
            foreach ($declared['tools'] as $tool) {
                $tools[$tool] = $scope;
            }
        }

        return $tools;
    }
}
