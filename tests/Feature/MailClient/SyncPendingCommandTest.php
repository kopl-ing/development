<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Kopling\Core\People\Person;
use Kopling\MailClient\Jobs\SyncMailAccount;
use Kopling\MailClient\MailAccount;

/**
 * @param  array<string, mixed>  $overrides
 */
function mailAccountFor(Person $person, array $overrides = []): MailAccount
{
    $internal = ['last_synced_at', 'syncing_since', 'last_sync_error'];

    $account = MailAccount::create([
        'person_id' => $person->id,
        'email_address' => 'x@example.test',
        'incoming_host' => 'imap.example.test',
        'incoming_port' => 993,
        'incoming_encryption' => 'ssl',
        'outgoing_host' => 'smtp.example.test',
        'outgoing_port' => 587,
        'outgoing_encryption' => 'starttls',
        'auth_type' => 'password',
        'username' => 'x@example.test',
        'password' => 'secret',
        ...array_diff_key($overrides, array_flip($internal)),
    ]);

    $account->forceFill(array_intersect_key($overrides, array_flip($internal)))->save();

    return $account;
}

it('dispatches sync for a never-synced account, and skips one synced recently', function () {
    Queue::fake();

    $person = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);

    $neverSynced = mailAccountFor($person);
    $recentlySynced = mailAccountFor($person, [
        'email_address' => 'y@example.test', 'username' => 'y@example.test',
        'last_synced_at' => now(),
    ]);

    $this->artisan('kopling:mail-client:sync-pending')->assertSuccessful();

    Queue::assertPushed(SyncMailAccount::class, fn (SyncMailAccount $job) => $job->mailAccountId === $neverSynced->id);
    Queue::assertNotPushed(SyncMailAccount::class, fn (SyncMailAccount $job) => $job->mailAccountId === $recentlySynced->id);
});

it('re-dispatches a stale-synced account', function () {
    Queue::fake();

    $person = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);

    $stale = mailAccountFor($person, ['last_synced_at' => now()->subMinutes(10)]);

    $this->artisan('kopling:mail-client:sync-pending');

    Queue::assertPushed(SyncMailAccount::class, fn (SyncMailAccount $job) => $job->mailAccountId === $stale->id);
});

it('skips an account that looks genuinely in flight, but re-picks up one that looks stuck', function () {
    Queue::fake();

    $person = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);

    $inFlight = mailAccountFor($person, ['syncing_since' => now()->subMinutes(2)]);
    $stuck = mailAccountFor($person, [
        'email_address' => 'y@example.test', 'username' => 'y@example.test',
        'syncing_since' => now()->subMinutes(20),
    ]);

    $this->artisan('kopling:mail-client:sync-pending');

    Queue::assertNotPushed(SyncMailAccount::class, fn (SyncMailAccount $job) => $job->mailAccountId === $inFlight->id);
    Queue::assertPushed(SyncMailAccount::class, fn (SyncMailAccount $job) => $job->mailAccountId === $stuck->id);
});
