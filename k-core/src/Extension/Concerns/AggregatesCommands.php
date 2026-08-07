<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Concerns;

use Illuminate\Console\Command;
use Kopling\Core\Extension\Contract\HasCommands;

trait AggregatesCommands
{
    /**
     * @return array<class-string>
     */
    public function commands(): array
    {
        if (($cached = $this->cache->get()) !== null) {
            return $cached['commands'];
        }

        $commands = [];

        foreach ($this->extensions() as $extension) {
            if (! $extension instanceof HasCommands) {
                continue;
            }

            foreach ($extension->commands() as $command) {
                if (! is_string($command) || ! is_subclass_of($command, Command::class)) {
                    throw new \UnexpectedValueException(
                        sprintf('HasCommands::commands() should only include Command class-strings, but %s found.', get_debug_type($command))
                    );
                }

                $commands[] = $command;
            }
        }

        return $commands;
    }
}
