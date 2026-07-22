<?php

declare(strict_types=1);

it('fails with a clear message when the docs content request is not mapped yet', function () {
    $this->artisan('kopling:docs:sync')
        ->assertFailed()
        ->expectsOutputToContain('is not mapped to an enabled drive');
});

it('syncs and reports how many pages were written', function () {
    $root = sys_get_temp_dir().'/kopling-docs-command-test-'.uniqid();
    mkdir($root, 0755, true);
    mapDocsDrive($root);
    writeDocFile($root, 'a.md', "---\ntitle: A\nsection: General\n---\nBody.");

    $this->artisan('kopling:docs:sync')
        ->assertSuccessful()
        ->expectsOutputToContain('Synced 1 doc page(s).');
});
