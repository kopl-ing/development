<?php

declare(strict_types=1);

namespace Kopling\Tags\Command;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Kopling\Core\Content\Moment;
use Kopling\Tags\Tag;

class SeedDemoTagsCommand extends Command
{
    protected $signature = 'kopling:tags:seed-demo';

    protected $description = 'Create a few demo tags and attach one or two to each moment';

    public function handle(): int
    {
        $palette = [
            ['Announcements', '#2b4a9b', 'bullhorn'],
            ['Guides', '#0d9488', 'compass'],
            ['Off-topic', '#7c3aed', 'mug-saucer'],
            ['Help', '#e8590c', 'hand-holding-medical'],
            ['Showcase', '#db2777', 'store'],
        ];

        $tags = collect($palette)->map(fn (array $def) => Tag::firstOrCreate(
            ['slug' => Str::slug($def[0])],
            ['name' => $def[0], 'color' => $def[1], 'icon' => $def[2]],
        ));

        Moment::query()->get()->each(function (Moment $moment) use ($tags) {
            $tags->random(random_int(1, 2))->each(
                fn (Tag $tag) => $tag->moments()->syncWithoutDetaching([$moment->id])
            );
        });

        $this->info("Seeded {$tags->count()} tags across ".Moment::query()->count().' moments.');

        return self::SUCCESS;
    }
}
