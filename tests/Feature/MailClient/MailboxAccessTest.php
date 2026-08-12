<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Kopling\Core\People\Group;
use Kopling\Core\People\Person;
use Kopling\MailClient\MailAccount;

/*
 * The Portal's own "access-mail" gate (Extension::portals()) wraps every mail-client route --
 * these prove that gate actually blocks a guest/unpermitted person, and that account
 * creation/deletion stay scoped to whoever owns the account, not just anyone signed in with
 * access-mail. Multi-tenant isolation here is the safety-critical part of this Panel: leaking
 * one person's connected mailbox to another would be a real incident, not a cosmetic bug.
 */

function personWithAccessMail(string $email = 'ada@example.test'): Person
{
    $person = Person::create(['name' => 'Ada', 'email' => $email, 'password' => 'secret']);

    $group = Group::create(['name' => 'Mail Users '.$email]);
    $group->givePermissionTo('kopling-mail-client::access-mail');
    $person->groups()->attach($group);

    return $person;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function mailAccountAttributes(array $overrides = []): array
{
    return [
        'email_address' => 'ada@example.test',
        'protocol' => 'imap',
        'incoming_host' => 'imap.example.test',
        'incoming_port' => 993,
        'incoming_encryption' => 'ssl',
        'outgoing_host' => 'smtp.example.test',
        'outgoing_port' => 587,
        'outgoing_encryption' => 'starttls',
        'auth_type' => 'password',
        'username' => 'ada@example.test',
        'password' => 'app-password',
        ...$overrides,
    ];
}

it('denies a guest entirely', function () {
    $this->get('/mail')->assertForbidden();

    $this->assertGuest();
});

it('denies a signed-in person without access-mail', function () {
    $person = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);

    $this->actingAs($person)->get('/mail')->assertForbidden();
});

it('shows the inbox for a person with access-mail', function () {
    $person = personWithAccessMail();

    $this->actingAs($person)->get('/mail')->assertOk();
});

it('connects a mailbox scoped to the signed-in person, with the password stored encrypted', function () {
    $person = personWithAccessMail();

    $this->actingAs($person)
        ->post('/mail/accounts', mailAccountAttributes(['label' => 'Personal']))
        ->assertRedirect(route('kopling-mail-client::mail/accounts'));

    $account = MailAccount::sole();

    expect($account->person_id)->toBe($person->id)
        ->and($account->is_default)->toBeTrue(); // first account for this person defaults to true

    // The model's own `encrypted` cast transparently decrypts back to plaintext on read --
    // asserting against the raw DB column is the only way to prove it isn't stored in the clear.
    $raw = DB::table('mail_accounts')->where('id', $account->id)->value('password');

    expect($raw)->not->toBe('app-password');
});

it('does not let one person delete another person\'s mail account', function () {
    $owner = personWithAccessMail('owner@example.test');
    $intruder = personWithAccessMail('intruder@example.test');

    $account = MailAccount::create([...mailAccountAttributes(), 'person_id' => $owner->id]);

    $this->actingAs($intruder)
        ->post("/mail/accounts/{$account->id}/delete")
        ->assertForbidden();

    expect(MailAccount::query()->whereKey($account->id)->exists())->toBeTrue();
});

it('does not let one person view another person\'s folder', function () {
    $owner = personWithAccessMail('owner@example.test');
    $intruder = personWithAccessMail('intruder@example.test');

    $account = MailAccount::create([...mailAccountAttributes(), 'person_id' => $owner->id]);
    $folder = $account->folders()->create(['name' => 'Inbox', 'path' => 'INBOX', 'type' => 'inbox']);

    $this->actingAs($intruder)
        ->get("/mail/accounts/{$account->id}/folders/{$folder->id}")
        ->assertForbidden();
});
