<?php

declare(strict_types=1);

namespace Kopling\Composer\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Ux\Editor\Rules\ValidDocument;

/**
 * The first `FormRequest` in this codebase -- validation was previously ad hoc `trim()`/
 * `abort_if()` in the controller, which was fine for a plain `<textarea>` but can't express
 * "well-formed ProseMirror JSON, only using currently-enabled node/mark types" the way this
 * needs to now that `body` is a TipTap document rather than plain text.
 */
class StoreMomentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route this backs already gates with the 'auth' middleware (see
        // k-extensions/composer/routes/web.php) -- nothing further to check here.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(Manager $manager): array
    {
        return [
            'title' => ['nullable', 'string', 'max:150'],
            'body' => ['required', 'string', new ValidDocument($manager->editorNodes())],
        ];
    }
}
