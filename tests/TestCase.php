<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Never commit an APP_KEY: a valid-looking key in the repo trips secret scanners for
        // no security gain (it encrypts nothing real). Generate a throwaway one per run when
        // the environment hasn't supplied its own -- set early, before any request encrypts.
        if (empty(config('app.key'))) {
            config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        }
    }
}
