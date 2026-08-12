<?php

declare(strict_types=1);

namespace Kopling\MailClient\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
     * Connection details only -- no IMAP/SMTP handshake yet (that's the sync/protocol layer,
     * not part of the Panel; see email-client.md's "Protocol layer" section). The account sits
     * with no folders/messages until a sync job (not yet built) populates them.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'email_address' => ['required', 'email', 'max:255'],
            'protocol' => ['required', 'in:imap,pop3'],
            'incoming_host' => ['required', 'string', 'max:255'],
            'incoming_port' => ['required', 'integer', 'between:1,65535'],
            'incoming_encryption' => ['required', 'in:ssl,starttls,none'],
            'outgoing_host' => ['required', 'string', 'max:255'],
            'outgoing_port' => ['required', 'integer', 'between:1,65535'],
            'outgoing_encryption' => ['required', 'in:ssl,starttls,none'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        MailAccount::create([
            ...$validated,
            'person_id' => $request->user()->id,
            'auth_type' => 'password',
            'is_default' => $request->boolean('is_default')
                || ! MailAccount::query()->where('person_id', $request->user()->id)->exists(),
        ]);

        return redirect()->route('kopling-mail-client::mail/accounts');
    }

    public function destroy(Request $request, MailAccount $account): RedirectResponse
    {
        abort_unless($account->person_id === $request->user()->id, 403);

        $account->delete();

        return redirect()->route('kopling-mail-client::mail/accounts');
    }
}
