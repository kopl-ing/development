<?php

declare(strict_types=1);

namespace Kopling\Composer\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Kopling\Core\Content\Moment;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Ux\Editor\PlainTextExtractor;
use Kopling\Core\Ux\Editor\Rules\ValidDocument;

class UpdateMomentRequest extends FormRequest
{
    /**
     * Only the moment's own author may edit it -- there's no Policy mechanism in this codebase
     * yet (see decisions.md), so this is a plain ownership check, the same shape
     * `ReportControlEntry`'s own `isOwnContent` already uses to decide whether to show a UX
     * entry, just enforced here as the real boundary rather than only hiding a button.
     */
    public function authorize(): bool
    {
        return $this->route('moment')?->person_id === Auth::id();
    }

    /**
     * Same normalization `StoreMomentRequest` applies -- the edit form's editor is always
     * mounted (no compose-mode toggle to switch away from), so a trivial empty-paragraph doc
     * still needs collapsing to null before `ValidDocument` ever sees it.
     */
    protected function prepareForValidation(): void
    {
        $body = $this->input('body');

        if ($body !== null && trim(PlainTextExtractor::extract((string) $body)) === '') {
            $this->merge(['body' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(Manager $manager): array
    {
        return $manager->mergeModelValidationRules(Moment::class, [
            'title' => ['required', 'string', 'max:150'],
            'body' => ['nullable', 'string', new ValidDocument($manager->editorNodes())],
        ])['rules'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return app(Manager::class)->mergeModelValidationRules(Moment::class, [])['messages'];
    }
}
