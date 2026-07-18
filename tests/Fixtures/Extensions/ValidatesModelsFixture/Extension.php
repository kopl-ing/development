<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\ValidatesModelsFixture;

use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ValidatesModels;

/**
 * Declares extra validation rules for a model it doesn't own -- mirrors how `reactions`
 * contributes `upvote_emoji`/`downvote_emoji` rules for `Kopling\Tags\Tag` without owning it.
 * The target ("Fixture\\Target") never needs to actually exist as a class -- unlike
 * `Extend\Model`, `ValidatesModels` is a plain aggregation `Manager` never runs `class_exists`
 * against, since nothing here touches the target class itself.
 */
class Extension extends AbstractExtension implements ValidatesModels
{
    public static function name(): string
    {
        return 'Validates Models Fixture';
    }

    public static function description(): string
    {
        return 'Declares extra validation rules for a fixture target, for testing ValidatesModels.';
    }

    /**
     * @return array<class-string, array{rules: array<string, array<int, string>>, messages: array<string, string>}>
     */
    public function modelValidationRules(): array
    {
        return [
            'Fixture\\Target' => [
                'rules' => [
                    'widget_color' => ['nullable', 'string', 'max:16'],
                ],
                'messages' => [
                    'widget_color.max' => 'Widget color is too long.',
                ],
            ],
        ];
    }
}
