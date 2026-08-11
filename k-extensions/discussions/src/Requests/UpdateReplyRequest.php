<?php

declare(strict_types=1);

namespace Kopling\Discussions\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Ux\Editor\Rules\ValidDocument;

class UpdateReplyRequest extends FormRequest
{
    /**
     * Only the reply's own author may edit it -- same plain ownership check
     * `UpdateMomentRequest::authorize()` uses, for the same reason: no Policy mechanism exists
     * in this codebase to hang an ability on instead (see decisions.md).
     */
    public function authorize(): bool
    {
        return $this->route('reply')?->person_id === Auth::id();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(Manager $manager): array
    {
        return [
            'body' => ['required', 'string', new ValidDocument($manager->editorNodes())],
        ];
    }
}
