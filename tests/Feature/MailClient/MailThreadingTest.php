<?php

declare(strict_types=1);

use Kopling\MailClient\MailAccount;
use Kopling\MailClient\MailFolder;
use Kopling\MailClient\MailMessage;

/**
 * @param  array<string, mixed>  $overrides
 */
function mailMessageIn(MailFolder $folder, array $overrides = []): MailMessage
{
    return $folder->messages()->create([
        'mail_account_id' => $folder->mail_account_id,
        'uid' => 1,
        'subject' => 'Hello',
        'from_address' => 'someone@example.test',
        'sent_at' => now(),
        'thread_id' => 'thread-abc',
        ...$overrides,
    ]);
}

it('collapses a thread down to one row -- its own latest message, with a message count', function () {
    $person = personWithAccessMail();
    $account = MailAccount::create([...mailAccountAttributes(), 'person_id' => $person->id]);
    $folder = $account->folders()->create(['name' => 'Inbox', 'path' => 'INBOX', 'type' => 'inbox']);

    mailMessageIn($folder, ['uid' => 1, 'sent_at' => now()->subHours(2), 'subject' => 'Original']);
    $latest = mailMessageIn($folder, ['uid' => 2, 'sent_at' => now(), 'subject' => 'Re: Original']);
    mailMessageIn($folder, ['uid' => 3, 'thread_id' => 'thread-other', 'sent_at' => now()]);

    $threads = MailMessage::latestPerThread(MailMessage::query())->get();

    expect($threads)->toHaveCount(2);

    $threadAbc = $threads->firstWhere('thread_id', 'thread-abc');

    expect($threadAbc->id)->toBe($latest->id)
        ->and((int) $threadAbc->thread_message_count)->toBe(2);
});

it('scopes a thread to the given person\'s own accounts only, even when someone else has a message in the same thread', function () {
    $personA = personWithAccessMail('a@example.test');
    $personB = personWithAccessMail('b@example.test');

    $accountA = MailAccount::create([...mailAccountAttributes(), 'person_id' => $personA->id]);
    $accountB = MailAccount::create([
        ...mailAccountAttributes(['email_address' => 'b@example.test', 'username' => 'b@example.test']),
        'person_id' => $personB->id,
    ]);

    $folderA = $accountA->folders()->create(['name' => 'Inbox', 'path' => 'INBOX', 'type' => 'inbox']);
    $folderB = $accountB->folders()->create(['name' => 'Inbox', 'path' => 'INBOX', 'type' => 'inbox']);

    // Both on the same real-world thread (a shared mailing list, say) -- same thread_id by
    // coincidence of both mailboxes syncing the same external conversation.
    $messageA = mailMessageIn($folderA);
    $messageB = mailMessageIn($folderB);

    $thread = MailMessage::inThread('thread-abc', $personA->id)->get();

    expect($thread->pluck('id'))->toContain($messageA->id)
        ->and($thread->pluck('id'))->not->toContain($messageB->id);
});
