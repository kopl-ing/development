<?php

declare(strict_types=1);

return [
    'blocked_domains' => [
        'label' => 'Blocked domains',
        'description' => 'One domain per line. Inbound activities from a blocked domain are rejected before their signature is even checked; outbound delivery to one is skipped.',
    ],
];
