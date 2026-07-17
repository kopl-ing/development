<?php

declare(strict_types=1);

namespace Kopling\Demo\Command;

use Illuminate\Console\Command;
use Kopling\Core\Content\Moment;
use Kopling\Core\Extension\Manager;
use Kopling\Core\People\Person;
use Kopling\Core\Ux\Editor\DocumentRenderer;

class SeedFakeDataCommand extends Command
{
    protected $signature = 'kopling:demo:seed-fake-data';
    protected $description = 'Seed fake data for the demo extension';

    public function handle(Manager $manager): int
    {
        $person = null;

        $resolvePerson = function () use (&$person): Person {
            return $person ??= $this->personOrNew();
        };

        $enabled = $manager->editorNodes();
        $actions = [$resolvePerson];

        for ($i = 0, $count = random_int(3, 9); $i < $count; $i++) {
            $actions[] = function () use ($resolvePerson, $enabled): void {
                // body is a ProseMirror JSON document, not plain text -- a single paragraph is
                // all a fake paragraph needs, same shape ComposerController::store() builds
                // from a real editor submission.
                $body = json_encode([
                    'type' => 'doc',
                    'content' => [[
                        'type' => 'paragraph',
                        'content' => [['type' => 'text', 'text' => fake()->paragraph()]],
                    ]],
                ]);

                Moment::create([
                    'person_id' => $resolvePerson()->id,
                    'title' => fake()->sentence(),
                    'body' => $body,
                    'body_html' => DocumentRenderer::render($body, $enabled),
                ]);
            };
        }

        shuffle($actions);

        foreach ($actions as $action) {
            $action();
        }

        return self::SUCCESS;
    }

    protected function personOrNew(): Person
    {
        if (Person::query()->count() > 5 && fake()->boolean(40)) {
            return Person::query()->inRandomOrder()->first();
        }

        return Person::create([
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
        ]);
    }
}
