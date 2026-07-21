<?php

declare(strict_types=1);

namespace Kopling\Poll;

use Illuminate\Validation\Rule;
use Kopling\Core\Content\Moment;
use Kopling\Core\Extend\Icon;
use Kopling\Core\Extend\Model;
use Kopling\Core\Extend\Permission;
use Kopling\Core\Extend\Relation;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\ExtendsModels;
use Kopling\Core\Extension\Contract\ExtendsPortals;
use Kopling\Core\Extension\Contract\HasIcons;
use Kopling\Core\Extension\Contract\HasPermissions;
use Kopling\Core\Extension\Contract\ValidatesModels;
use Kopling\Core\Portal\PortalExtension;
use Kopling\Core\Ux\Card\Body;
use Kopling\Core\Ux\Compose\Modes;

class Extension extends AbstractExtension implements ChangesUx, ExtendsModels, ExtendsPortals, HasIcons, HasPermissions, ValidatesModels
{
    public static function name(): string
    {
        return 'Poll';
    }

    public static function description(): string
    {
        return 'Attach a poll to a moment -- single or multiple choice, optional group targeting, optional closing time.';
    }

    public function icons(): array
    {
        return [
            new Icon(id: 'poll', label: 'Poll', default: 'fas-square-poll-horizontal'),
        ];
    }

    public function permissions(): array
    {
        return [
            new Permission(
                id: 'vote',
                label: __('kopling-poll::permissions.vote.label'),
                description: __('kopling-poll::permissions.vote.description'),
                default: true,
            ),
        ];
    }

    public function ux(): Ux
    {
        return Ux::make()
            ->add('kopling-poll::compose-mode', [
                'icon' => 'kopling-poll::poll',
                'label' => __('kopling-poll::messages.mode_label'),
            ])
            ->in(Modes::SLOT)
            ->as('vote')
            ->add('kopling-poll::widget')
            ->in(Body::SLOT)
            ->as('poll')
            ->after('kopling-core::content');
    }

    /**
     * A moment can be composite -- text plus a poll in the same submission, since every mode's
     * panel stays in the DOM regardless of which one is visually active (see the composer Modes
     * component). So `compose_mode` says nothing about whether *this* extension's fields were
     * actually filled in -- only whether poll's own fields carry real content does. Question and
     * options are required only when this returns true; the `saved()` hook only creates a Poll
     * row when this returns true.
     */
    protected function attemptedPoll(): bool
    {
        if (trim((string) request()->input('poll_question', '')) !== '') {
            return true;
        }

        $fields = array_merge(
            request()->input('poll_options', []),
            request()->input('poll_option_emoji', []),
        );

        foreach ($fields as $value) {
            if (trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Entirely composer's own `title`/`body` fields untouched -- poll's fields are their own
     * keys, required only when `attemptedPoll()` says this extension's fields carry real content,
     * so two modes conditioning the same field never collide (see
     * `Manager::mergeModelValidationRules()`'s last-registered-wins behavior for a repeated key).
     *
     * @return array<class-string, array{rules: array, messages: array}>
     */
    public function modelValidationRules(): array
    {
        $attempted = $this->attemptedPoll();

        return [
            Moment::class => [
                'rules' => [
                    'poll_question' => [Rule::requiredIf($attempted), 'nullable', 'string', 'max:255'],
                    'poll_options' => [
                        Rule::requiredIf($attempted), 'array', 'min:2',
                        function (string $attribute, mixed $value, \Closure $fail) use ($attempted) {
                            if (! $attempted) {
                                return;
                            }

                            $emoji = array_values(request()->input('poll_option_emoji', []));

                            foreach (array_values((array) $value) as $position => $label) {
                                if (trim((string) $label) === '' && trim((string) ($emoji[$position] ?? '')) === '') {
                                    $fail(__('kopling-poll::messages.option_needs_text_or_emoji'));

                                    return;
                                }
                            }
                        },
                    ],
                    'poll_options.*' => ['nullable', 'string', 'max:100'],
                    'poll_option_emoji' => ['array'],
                    'poll_option_emoji.*' => ['nullable', 'string', 'max:8'],
                    'poll_multiple' => ['sometimes', 'boolean'],
                    'poll_max_choices' => ['nullable', 'integer', 'min:1'],
                    'poll_results_visibility' => ['nullable', Rule::in(Poll::VISIBILITY_OPTIONS)],
                    'poll_groups' => ['array'],
                    'poll_groups.*' => ['uuid', 'exists:groups,id'],
                    'poll_closes_at' => ['nullable', 'date'],
                ],
                'messages' => [],
            ],
        ];
    }

    /**
     * `saved()`, not `creating()`/`saving()` -- needs the Moment's real id before it can create
     * `polls.moment_id`. Guarded on `attemptedPoll()`, not `request()->has('poll_question')` --
     * that key is present, empty, on *every* submission regardless of mode (see its own
     * docblock), so `has()` alone can't tell an attempted poll apart from an untouched one.
     *
     * @return array<Model>
     */
    public function models(): array
    {
        return [
            (new Model(Moment::class))
                ->relation((new Relation)->hasOne('poll', Poll::class)->eagerLoad())
                ->saved(function (Moment $moment) {
                    if (! $this->attemptedPoll()) {
                        return;
                    }

                    $poll = Poll::updateOrCreate(
                        ['moment_id' => $moment->id],
                        [
                            'question' => request()->input('poll_question'),
                            'multiple_choice' => request()->boolean('poll_multiple'),
                            'max_choices' => request()->input('poll_max_choices'),
                            'results_visibility' => request()->input('poll_results_visibility', Poll::VISIBILITY_AFTER_VOTE),
                            'closes_at' => request()->input('poll_closes_at'),
                        ],
                    );

                    $poll->options()->delete();

                    $labels = array_values(request()->input('poll_options', []));
                    $emoji = array_values(request()->input('poll_option_emoji', []));

                    foreach ($labels as $position => $label) {
                        $poll->options()->create([
                            'label' => $label !== '' ? $label : null,
                            'emoji' => ($emoji[$position] ?? '') !== '' ? $emoji[$position] : null,
                            'position' => $position,
                        ]);
                    }

                    $poll->groups()->sync(request()->input('poll_groups', []));

                    $moment->setRelation('poll', $poll->load('options', 'groups'));
                }),
        ];
    }

    /**
     * @return array<PortalExtension>
     */
    public function extendsPortals(): array
    {
        return [
            new PortalExtension('kopling-core::community')
                ->routes(__DIR__.'/../routes/web.php'),
        ];
    }
}
