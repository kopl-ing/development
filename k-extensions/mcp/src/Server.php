<?php

declare(strict_types=1);

namespace Kopling\Mcp;

use Kopling\Core\Settings\Settings;
use Kopling\Mcp\Methods\CallTool;
use Laravel\Mcp\Server as LaravelServer;

class Server extends LaravelServer
{
    protected string $version = '1.0.0';

    protected function boot(): void
    {
        $this->name = (string) Settings::get('kopling-core::community-name', 'Kopling');
        $this->instructions = __('kopling-mcp::messages.instructions', ['site' => $this->name]);
        $this->tools = array_keys(app(Registry::class)->tools());
        $this->addMethod('tools/call', CallTool::class);
    }
}
