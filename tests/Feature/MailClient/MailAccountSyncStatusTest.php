<?php

declare(strict_types=1);

use Kopling\MailClient\MailAccount;

/*
 * syncing_since/last_sync_error/last_synced_at are deliberately not in $fillable (internal
 * job-managed state, never user-submitted) -- setRawAttributes() bypasses that guard the same
 * way the sync jobs' own forceFill() does, without needing to persist to the DB just to test a
 * computed property.
 */

it('is pending when never synced', function () {
    expect((new MailAccount)->syncStatus())->toBe('pending');
});

it('is syncing while syncing_since is set', function () {
    $account = new MailAccount;
    $account->setRawAttributes(['syncing_since' => now()->toDateTimeString()]);

    expect($account->syncStatus())->toBe('syncing');
});

it('is failed when there is an error not yet cleared by a later success', function () {
    $account = new MailAccount;
    $account->setRawAttributes([
        'last_sync_error' => 'Connection refused',
        'last_synced_at' => now()->subDay()->toDateTimeString(),
    ]);

    expect($account->syncStatus())->toBe('failed');
});

it('is synced once last_synced_at is set and no error is pending', function () {
    $account = new MailAccount;
    $account->setRawAttributes(['last_synced_at' => now()->toDateTimeString()]);

    expect($account->syncStatus())->toBe('synced');
});
