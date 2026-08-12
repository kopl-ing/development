<?php

declare(strict_types=1);

namespace Kopling\MailClient\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Kopling\Core\Ux\Context;
use Kopling\MailClient\MailAccount;
use Kopling\MailClient\MailFolder;
use Kopling\MailClient\MailMessage;

class MailboxController
{
    /**
     * "All Inboxes" -- the default landing view, pooling every connected account's own
     * inbox-typed folder (MailFolder::TYPE_INBOX) into one merged list.
     */
    public function index(Request $request): View
    {
        return $this->render(
            'all',
            $this->scoped($request)->whereHas('folder', fn (Builder $query) => $query->where('type', MailFolder::TYPE_INBOX)),
        );
    }

    public function smartView(Request $request, string $view): View
    {
        $query = match ($view) {
            'flagged' => $this->scoped($request)->whereHas('flags', fn (Builder $query) => $query->where('flagged', true)),
            'sent' => $this->scoped($request)->whereHas('folder', fn (Builder $query) => $query->where('type', MailFolder::TYPE_SENT)),
        };

        return $this->render($view, $query);
    }

    /**
     * Drilling into one connected account's own real folder tree -- the escape hatch out of the
     * unified smart views above, for a folder with no special role (see MailFolder::TYPE_*) or
     * just to browse one account specifically.
     */
    public function folder(Request $request, MailAccount $account, MailFolder $folder): View
    {
        abort_unless($account->person_id === $request->user()->id, 403);
        abort_unless($folder->mail_account_id === $account->id, 404);

        return $this->render($folder->id, $folder->messages()->getQuery(), $account, $folder);
    }

    private function scoped(Request $request): Builder
    {
        return MailMessage::query()->whereHas(
            'account',
            fn (Builder $query) => $query->where('person_id', $request->user()->id),
        );
    }

    private function render(string $activeView, Builder $messages, ?MailAccount $account = null, ?MailFolder $folder = null): View
    {
        // Shared with <x-k::page.pagination> below via the same Context instance, so paging and
        // listing don't run the count+select query twice -- see Context::getSubjectPaginator().
        $context = new Context(subject: $messages->with(['account', 'flags'])->orderByDesc('sent_at'));

        return view('kopling-mail-client::inbox', [
            'context' => $context,
            'activeView' => $activeView,
            'account' => $account,
            'folder' => $folder,
            'selected' => null,
        ]);
    }
}
