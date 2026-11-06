<?php

declare(strict_types=1);

namespace Kopling\Mcp;

use Illuminate\Support\Str;
use Kopling\Core\Settings\Settings;
use Kopling\OAuth\Person;

class Audit
{
    public const FREE_TEXT_LIMIT = 200;

    public ?string $teamId = null;

    /**
     * @param array<string, mixed> $arguments
     */
    public function record(?Person $person, ?string $clientId, string $tool, array $arguments, string $outcome): void
    {
        ToolCall::create([
            'person_id' => $person?->id,
            'client_id' => $clientId,
            'tool' => Str::limit($tool, 120, ''),
            'team_id' => $this->teamId,
            'arguments' => self::truncate($arguments),
            'outcome' => $outcome,
        ]);

        if (random_int(1, 100) === 1) {
            self::prune();
        }
    }

    public static function prune(): void
    {
        $days = max(1, (int) Settings::get(ServiceProvider::ID.'::audit-retention-days', 90));
        ToolCall::where('created_at', '<', now()->subDays($days))->delete();
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private static function truncate(array $arguments): array
    {
        return array_map(fn ($value) => match (true) {
            is_string($value) => Str::limit($value, self::FREE_TEXT_LIMIT),
            is_array($value) => self::truncate($value),
            default => $value,
        }, $arguments);
    }
}
