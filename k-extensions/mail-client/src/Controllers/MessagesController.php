<?php

declare(strict_types=1);

namespace Kopling\MailClient\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Kopling\MailClient\MailMessage;

class MessagesController
{
    /**
     * Selecting a thread card from the list swaps only the reading pane
     * (`hx-target="#mail-reading-pane"` on each card -- see messages/list.blade.php) -- this
     * returns just that fragment for the htmx case. Shows the whole conversation, not just the
     * one message that was clicked (MailMessage::inThread()) -- same "moment + its replies"
     * shape as Discussions, styled similarly but built from mail-client's own data, not real
     * Moment/Reply rows (see email-client.md -- reusing those would leak private mail to every
     * extension that generically hooks into any Moment). A direct or refreshed URL (no
     * HX-Request header) falls back to rendering it inside the "All Inboxes" unified view -- a
     * known v1 simplification, not necessarily the folder/smart-view it was actually opened from.
     */
    public function show(Request $request, MailMessage $message): View
    {
        abort_unless($message->account->person_id === $request->user()->id, 403);

        $thread = MailMessage::inThread($message->thread_id, $request->user()->id)->get();

        if ($request->header('HX-Request')) {
            return view('kopling-mail-client::messages.show', ['thread' => $thread]);
        }

        return app(MailboxController::class)->index($request)->with('selected', $thread);
    }
}
