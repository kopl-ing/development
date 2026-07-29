<?php

declare(strict_types=1);

namespace Kopling\Widgets\Ux;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\Component;
use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;
use Kopling\Core\Ux\Context;

/**
 * A few cheap counts, cached briefly -- the rail renders on every community page, so this never
 * wants to be per-request. Reply/Reaction counts only when those extensions exist, referenced
 * by fully-qualified name rather than `use`-imported so this still loads without them installed.
 */
class PulseWidget extends Component
{
    public function __construct(
        public array $data = [],
        public ?Context $context = null,
    ) {
    }

    public function render(): View
    {
        return view('kopling-widgets::ux.pulse-widget', [
            'stats' => $this->stats(),
        ]);
    }

    protected function stats(): array
    {
        return Cache::remember('kopling-widgets.pulse', 60, function () {
            $stats = ['moments' => Moment::count(), 'people' => Person::count()];

            if (class_exists(\Kopling\Discussions\Reply::class)) {
                $stats['replies'] = \Kopling\Discussions\Reply::count();
            }

            if (class_exists(\Kopling\Reactions\Reaction::class)) {
                $stats['reactions'] = \Kopling\Reactions\Reaction::count();
            }

            return $stats;
        });
    }
}
