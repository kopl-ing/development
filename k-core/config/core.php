<?php

declare(strict_types=1);

return [
    /*
     * Font Awesome's GraphQL API (https://docs.fontawesome.com/apis/graphql) backs the icon
     * search endpoint behind Ux\Form\IconPicker (see Ux\Form\IconSearch\FontAwesomeIconSearch).
     * Most fields, including icon search, are public and need no token -- this is optional,
     * for whatever raising Font Awesome's own anonymous rate limit requires. Get one from
     * https://fontawesome.com/account/general (the "API Tokens" section).
     */
    'font_awesome_token' => env('FONT_AWESOME_API_TOKEN'),
];
