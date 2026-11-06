<?php

declare(strict_types=1);

return [
    'scope' => 'Use AI assistant tools on this site',
    'instructions' => 'Tools for :site. Every tool acts as the person who connected you and can never do more than they can themselves. Ask before changing or deleting anything.',
    'outcome' => [
        'ok' => 'done',
        'denied' => 'refused',
        'error' => 'failed',
    ],
    'team_calls' => [
        'heading' => 'AI assistant activity',
        'help' => 'The latest things assistants did for this team, by whom and with which app, including site admins.',
    ],
    'admin' => [
        'nav' => 'AI assistants (MCP)',
        'endpoint' => 'Assistants connect to :url',
        'tools' => 'Available tools',
        'no_tools' => 'No extension offers tools yet.',
        'calls' => 'Recent tool calls',
        'no_calls' => 'No tool calls yet.',
        'removed' => 'removed person',
    ],
];
