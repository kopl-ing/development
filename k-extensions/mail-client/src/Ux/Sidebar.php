<?php

declare(strict_types=1);

namespace Kopling\MailClient\Ux;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;
use Kopling\Core\Ux\Context;
use Kopling\MailClient\MailAccount;

/**
 * Two-tier sidebar per email-client.md's UX plan, step 4: unified smart views on top (queries
 * spanning every connected account, see MailboxController), each connected account's own real
 * folder tree below that. `$data`/`$context` are unused but required -- `portal.slot.blade.php`
 * always passes both to whatever `<x-dynamic-component>` a slot resolves.
 */
class Sidebar extends Component
{
    public array $data = [];

    public ?Context $context = null;

    public function render(): View
    {
        $person = Auth::user();

        return view('kopling-mail-client::ux.sidebar', [
            'accounts' => $person
                ? MailAccount::query()->where('person_id', $person->id)->with('folders')->orderBy('label')->get()
                : collect(),
        ]);
    }
}
