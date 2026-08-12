<?php

declare(strict_types=1);

namespace Kopling\MailClient\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Kopling\MailClient\MailMessage;

class MessagesController
{
    /**
     * Selecting a message from the list swaps only the reading pane (`hx-target="#mail-reading-pane"`
     * on each row -- see messages/list.blade.php) -- this returns just that fragment for the htmx
     * case. A direct or refreshed URL to a single message (no HX-Request header) falls back to
     * rendering it inside the "All Inboxes" unified view -- a known v1 simplification, not
     * necessarily the folder/smart-view the message was actually opened from.
     */
    public function show(Request $request, MailMessage $message): View
    {
        abort_unless($message->account->person_id === $request->user()->id, 403);

        $message->load(['account', 'folder', 'flags']);

        if ($request->header('HX-Request')) {
            return view('kopling-mail-client::messages.show', ['message' => $message]);
        }

        return app(MailboxController::class)->index($request)->with('selected', $message);
    }
}
