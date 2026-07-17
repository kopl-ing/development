<?php

declare(strict_types=1);

namespace Kopling\Discussions\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Ux\Editor\Rules\ValidDocument;

class StoreReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Auth is already enforced upstream: a guest POST throws AuthenticationException,
        // turned into an HX-Redirect to login by core's RedirectUnauthenticated (see
        // DiscussionController::reply()'s own docblock) -- nothing further to check here.
        return true;
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
