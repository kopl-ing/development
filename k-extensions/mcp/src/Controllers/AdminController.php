<?php

declare(strict_types=1);

namespace Kopling\Mcp\Controllers;

use Illuminate\Contracts\View\View;
use Kopling\Core\Ux\Context;
use Kopling\Mcp\Registry;
use Kopling\Mcp\ToolCall;

class AdminController
{
    public function index(Registry $registry): View
    {
        return view('kopling-mcp::admin.index', [
            'toolsets' => $registry->toolsets(),
            'tools' => $registry->tools(),
            'scopes' => $registry->scopes(),
            'context' => new Context(subject: ToolCall::with(['person', 'client'])->latest()),
        ]);
    }
}
