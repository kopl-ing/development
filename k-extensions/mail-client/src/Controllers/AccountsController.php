<?php

declare(strict_types=1);

namespace Kopling\MailClient\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Kopling\MailClient\Jobs\SyncMailAccount;
use Kopling\MailClient\MailAccount;

class AccountsController
{
    public function index(Request $request): View
    {
        return view('kopling-mail-client::accounts.index', [
            'accounts' => MailAccount::query()->where('person_id', $request->user()->id)->orderBy('label')->get(),
        ]);
    }

    /**
     * Connection details only -- validation checks they're well-formed, not that they actually
     * connect (no IMAP handshake here). The real first attempt happens via the dispatched
     * SyncMailAccount job below -- a bad host/credential surfaces as `last_sync_error` on the
     * account afterwards, not as a form validation error at connect time.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'email_address' => ['required', 'email', 'max:255'],
            'incoming_host' => ['required', 'string', 'max:255'],
            'incoming_port' => ['required', 'integer', 'between:1,65535'],
            'incoming_encryption' => ['required', 'in:ssl,starttls,none'],
            'outgoing_host' => ['required', 'string', 'max:255'],
            'outgoing_port' => ['required', 'integer', 'between:1,65535'],
            'outgoing_encryption' => ['required', 'in:ssl,starttls,none'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $account = MailAccount::create([
            ...$validated,
            'person_id' => $request->user()->id,
            'auth_type' => 'password',
            'is_default' => $request->boolean('is_default')
                || ! MailAccount::query()->where('person_id', $request->user()->id)->exists(),
        ]);

        SyncMailAccount::dispatch($account->id);

        return redirect()->route('kopling-mail-client::mail/accounts');
    }

    public function destroy(Request $request, MailAccount $account): RedirectResponse
    {
        abort_unless($account->person_id === $request->user()->id, 403);

        $account->delete();

        return redirect()->route('kopling-mail-client::mail/accounts');
    }

    public function status(Request $request, MailAccount $account): View
    {
        abort_unless($account->person_id === $request->user()->id, 403);

        return view('kopling-mail-client::accounts.status', ['account' => $account]);
    }
}
