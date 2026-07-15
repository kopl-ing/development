<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\ModelHooker;

use Kopling\Core\Extend\Model as ExtendModel;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ExtendsModels;

/**
 * A fixture extension exercising `ExtendsModels`' `creating()`/`saving()` end to end: a
 * `creating()` hook that stamps a column the caller never set (and can reject a create outright)
 * and a `saving()` hook that transforms an attribute on both insert and update.
 */
class Extension extends AbstractExtension implements ExtendsModels
{
    public static function name(): string
    {
        return 'Model Hooker Fixture';
    }

    public static function description(): string
    {
        return 'Adds creating()/saving() hooks to a fixture model, for testing ExtendsModels.';
    }

    /**
     * @return array<ExtendModel>
     */
    public function models(): array
    {
        return [
            (new ExtendModel(Message::class))
                ->creating(function (Message $message) {
                    // `saving` (below) fires before `creating` on insert, so `body` may already
                    // be transformed by the time this runs -- compare case-insensitively.
                    if (strtolower((string) $message->body) === 'reject-me') {
                        return false;
                    }

                    $message->ip = '127.0.0.1';
                })
                ->saving(function (Message $message) {
                    $message->body = strtoupper((string) $message->body);
                }),
        ];
    }
}
