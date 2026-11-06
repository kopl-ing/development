<?php

declare(strict_types=1);

return [
    'revoke' => 'Intrekken',
    'revoked' => 'Toegang ingetrokken.',
    'authorize' => [
        'title' => ':client wil toegang tot je account',
        'intro' => ':client kan dan het volgende doen als :name. Het kan nooit meer dan jij zelf.',
        'not_allowed' => 'niet toegestaan voor jou',
        'no_scopes' => 'Het vraagt geen specifieke rechten.',
        'return_to' => 'Hierna ga je terug naar :host. Ga alleen verder als je het koppelen daar begon.',
        'revoke_hint' => 'Je kunt de koppeling altijd weer verbreken bij Instellingen.',
        'approve' => 'Toestaan',
        'deny' => 'Weigeren',
    ],
    'apps' => [
        'heading' => 'Gekoppelde apps',
        'help' => 'Apps en assistenten die namens jou iets mogen doen. Ze kunnen nooit meer dan jij zelf.',
        'since' => 'Gekoppeld sinds :date',
        'confirm_revoke' => ':client ontkoppelen? Het verliest meteen de toegang.',
        'personal' => 'Persoonlijke tokens',
        'personal_help' => 'Voor tools zonder inlogscherm. Behandel een token als een wachtwoord.',
        'expires' => 'Geldig tot :date',
        'token_name' => 'Naam',
        'token_scopes' => 'Wat mag het doen?',
        'create_token' => 'Token aanmaken',
        'new_token' => 'Kopieer dit token nu. Het wordt niet nog een keer getoond.',
    ],
    'admin' => [
        'nav' => 'Gekoppelde apps',
        'keys_missing' => 'OAuth heeft nog geen sleutels om mee te ondertekenen, dus er kan geen app koppelen.',
        'create_keys' => 'Sleutels aanmaken',
        'keys_created' => 'Sleutels aangemaakt.',
        'clients' => 'Apps',
        'no_clients' => 'Er heeft nog geen app gekoppeld.',
        'registered' => 'Geregistreerd op :date',
        'people' => '{0} niet in gebruik|{1} gebruikt door 1 persoon|[2,*] gebruikt door :count personen',
        'revoked' => 'ingetrokken',
        'confirm_revoke_client' => ':client voor iedereen intrekken? Alle tokens ervan werken dan niet meer.',
        'tokens' => 'Actieve tokens',
        'no_tokens' => 'Geen actieve tokens.',
    ],
];
