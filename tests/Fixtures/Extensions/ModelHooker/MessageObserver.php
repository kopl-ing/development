<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\ModelHooker;

class MessageObserver
{
    /**
     * @var array<int, mixed>
     */
    public static array $deletingLog = [];

    public function deleting(Message $message): bool
    {
        static::$deletingLog[] = $message->id;

        return $message->body !== 'KEEP-ME';
    }
}
