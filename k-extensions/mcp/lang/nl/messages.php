<?php

declare(strict_types=1);

return [
    'scope' => 'AI-assistent tools op deze site gebruiken',
    'instructions' => 'Tools voor :site. Elke tool werkt als de persoon die jou koppelde en kan nooit meer dan die zelf. Vraag het eerst voordat je iets wijzigt of verwijdert.',
    'outcome' => [
        'ok' => 'gedaan',
        'denied' => 'geweigerd',
        'error' => 'mislukt',
    ],
    'team_calls' => [
        'heading' => 'Activiteit van AI-assistenten',
        'help' => 'Wat assistenten het laatst voor dit team deden, door wie en met welke app, ook door sitebeheerders.',
    ],
    'admin' => [
        'nav' => 'AI-assistenten (MCP)',
        'endpoint' => 'Assistenten koppelen via :url',
        'tools' => 'Beschikbare tools',
        'no_tools' => 'Nog geen extensie biedt tools aan.',
        'calls' => 'Recente tool-aanroepen',
        'no_calls' => 'Nog geen tool-aanroepen.',
        'removed' => 'verwijderd persoon',
    ],
];
