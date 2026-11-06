<?php

declare(strict_types=1);

namespace Kopling\Mcp\Ux;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\Ux\Context;
use Kopling\Mcp\ToolCall;

/**
 * Assistant activity about the context's subject (a team), for whoever the host page shows it to.
 */
class TeamCalls extends Component
{
    public const LIMIT = 20;

    public function __construct(
        public array $data = [],
        public ?Context $context = null,
    ) {
    }

    public function render(): View
    {
        $teamId = $this->context?->getSubject()?->getKey();

        return view('kopling-mcp::ux.team-calls', [
            'calls' => $teamId === null ? collect() : ToolCall::where('team_id', $teamId)->with(['person', 'client'])->latest()->limit(self::LIMIT)->get(),
        ]);
    }
}
