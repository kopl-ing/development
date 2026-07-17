<?php

declare(strict_types=1);

namespace Kopling\Discussions\Command;

use Illuminate\Console\Command;
use Kopling\Core\Content\Moment;
use Kopling\Core\Extension\Manager;
use Kopling\Core\People\Person;
use Kopling\Core\Ux\Editor\DocumentRenderer;
use Kopling\Discussions\Reply;

class SeedDemoRepliesCommand extends Command
{
    protected $signature = 'kopling:discussions:seed-demo';

    protected $description = 'Seed a handful of demo replies across moments';

    public function handle(Manager $manager): int
    {
        $people = Person::query()->get();

        if ($people->isEmpty()) {
            $this->warn('No people to author replies -- run kopling:demo:seed-fake-data first.');

            return self::FAILURE;
        }

        $enabled = $manager->editorNodes();
        $total = 0;

        Moment::query()->get()->each(function (Moment $moment) use ($people, $enabled, &$total) {
            $count = random_int(0, 4);

            for ($i = 0; $i < $count; $i++) {
                // body is a ProseMirror JSON document, not plain text -- a single paragraph is
                // all a fake sentence needs, same shape ComposerController::store()/
                // DiscussionController::reply() build from a real editor submission.
                $body = json_encode([
                    'type' => 'doc',
                    'content' => [[
                        'type' => 'paragraph',
                        'content' => [['type' => 'text', 'text' => fake()->sentence(random_int(6, 20))]],
                    ]],
                ]);

                Reply::create([
                    'moment_id' => $moment->id,
                    'person_id' => $people->random()->id,
                    'body' => $body,
                    'body_html' => DocumentRenderer::render($body, $enabled),
                ]);
                $total++;
            }
        });

        $this->info("Seeded {$total} replies across ".Moment::query()->count().' moments.');

        return self::SUCCESS;
    }
}
