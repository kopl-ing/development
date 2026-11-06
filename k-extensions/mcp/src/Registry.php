<?php

declare(strict_types=1);

namespace Kopling\Mcp;

use InvalidArgumentException;
use Kopling\Core\Extend\Mcp\Toolset;
use Kopling\Core\Extension\Manager;

/**
 * Every enabled extension's toolsets, with each tool's prefixed name and scope.
 */
class Registry
{
    public const NAME_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    public const MAX_NAME_LENGTH = 64;

    /** @var array<Toolset>|null */
    private ?array $toolsets = null;

    /** @var array<class-string, array{name: string, scope: string, extension: string}>|null */
    private ?array $tools = null;

    public function __construct(private readonly Manager $manager)
    {
    }

    /**
     * @return array<Toolset>
     */
    public function toolsets(): array
    {
        return $this->toolsets ??= $this->manager->mcpToolsets();
    }

    /**
     * @return array<string, string> scope id => description
     */
    public function scopes(): array
    {
        $scopes = [];
        foreach ($this->toolsets() as $toolset) {
            foreach ($toolset->scopes as $id => $scope) {
                $scopes[$id] = $scope['description'];
            }
        }

        return $scopes;
    }

    /**
     * Throws on a tool that isn't a `Kopling\Mcp\Tool`, an invalid local name, or a full name past the 64 characters MCP clients accept.
     *
     * @return array<class-string, array{name: string, scope: string, extension: string}>
     */
    public function tools(): array
    {
        if ($this->tools !== null) {
            return $this->tools;
        }

        $tools = [];
        foreach ($this->toolsets() as $toolset) {
            foreach ($toolset->tools() as $class => $scope) {
                if (! is_subclass_of($class, Tool::class)) {
                    throw new InvalidArgumentException("[{$toolset->extension}] MCP tool [{$class}] must extend ".Tool::class.'.');
                }

                $local = app($class)->localName();
                $name = $toolset->extension.'__'.$local;

                if (! preg_match(self::NAME_PATTERN, $local)) {
                    throw new InvalidArgumentException("[{$toolset->extension}] MCP tool name [{$local}] must be kebab case, a-z and 0-9.");
                }
                if (strlen($name) > self::MAX_NAME_LENGTH) {
                    throw new InvalidArgumentException("[{$toolset->extension}] MCP tool name [{$name}] is longer than ".self::MAX_NAME_LENGTH.' characters.');
                }

                $tools[$class] = ['name' => $name, 'scope' => $scope, 'extension' => $toolset->extension];
            }
        }

        $duplicate = collect($tools)->pluck('name')->duplicates()->first();
        if ($duplicate !== null) {
            throw new InvalidArgumentException("MCP tool name [{$duplicate}] is registered twice.");
        }

        return $this->tools = $tools;
    }

    /**
     * @return array{name: string, scope: string, extension: string}|null
     */
    public function tool(string $class): ?array
    {
        return $this->tools()[$class] ?? null;
    }

    public function known(string $name): bool
    {
        return in_array($name, array_column($this->tools(), 'name'), true);
    }
}
