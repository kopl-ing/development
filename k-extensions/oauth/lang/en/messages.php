<?php

declare(strict_types=1);

return [
    'revoke' => 'Revoke',
    'revoked' => 'Access revoked.',
    'authorize' => [
        'title' => ':client wants access to your account',
        'intro' => ':client will be able to do the following as :name. It can never do more than you can yourself.',
        'not_allowed' => 'not allowed for you',
        'no_scopes' => 'It asks for no specific permissions.',
        'return_to' => 'After this you go back to :host. Only continue if you started connecting from there.',
        'revoke_hint' => 'You can disconnect it at any time under Settings.',
        'approve' => 'Allow',
        'deny' => 'Deny',
    ],
    'apps' => [
        'heading' => 'Connected apps',
        'help' => 'Apps and assistants that can act on your behalf. They can never do more than you can yourself.',
        'since' => 'Connected since :date',
        'confirm_revoke' => 'Disconnect :client? It loses access straight away.',
        'personal' => 'Personal tokens',
        'personal_help' => 'For tools without a sign-in screen. Treat a token like a password.',
        'expires' => 'Valid until :date',
        'token_name' => 'Name',
        'token_scopes' => 'What may it do?',
        'create_token' => 'Create token',
        'new_token' => 'Copy this token now. It won\'t be shown again.',
    ],
    'admin' => [
        'nav' => 'Connected apps',
        'keys_missing' => 'OAuth has no signing keys yet, so no app can connect.',
        'create_keys' => 'Create keys',
        'keys_created' => 'Keys created.',
        'clients' => 'Apps',
        'no_clients' => 'No apps have connected yet.',
        'registered' => 'Registered :date',
        'people' => '{0} not in use|{1} used by 1 person|[2,*] used by :count people',
        'revoked' => 'revoked',
        'confirm_revoke_client' => 'Revoke :client for everyone? Every token it holds stops working.',
        'tokens' => 'Active tokens',
        'no_tokens' => 'No active tokens.',
    ],
];
