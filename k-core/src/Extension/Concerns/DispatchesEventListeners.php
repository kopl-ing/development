<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Concerns;

use Kopling\Core\Extension\Contract\ListensToEvents;

trait DispatchesEventListeners
{
    public function listeners(): void
    {
        foreach ($this->extensions() as $package => $extension) {
            if (! $extension instanceof ListensToEvents) {
                continue;
            }

            foreach ($extension->listen() as $event => $listener) {
                match (true) {
                    is_string($event) => $this->events->listen($event, $listener),
                    default => $this->events->subscribe($listener)
                };
            }
        }
    }
}
