<?php

declare(strict_types=1);

use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;
use Symfony\Component\DomCrawler\Crawler;

/*
 * A worded reaction is one `reactions` row rendered in exactly one place -- its own chip
 * (`words.blade.php`) -- never also as a plain rail pill for the same emoji. Regression coverage
 * for a real report: adding emoji+word showed both a rail pill *and* a chip for the identical
 * reaction, because `Reaction::state()`'s per-emoji counts included worded rows too. See
 * decisions.md's "worded reaction rendered twice" entries (including the follow-up correction)
 * for the full story.
 */

it('renders a solo worded reaction as only its chip -- no rail pill for that emoji at all', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada-worded-dup@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);
    $reactor = Person::create(['name' => 'Bob', 'email' => 'bob-worded-dup@example.test', 'password' => 'secret']);

    $response = $this->actingAs($reactor)
        ->post("/_xhr/kopling-reactions/moment/{$moment->id}/word", ['emoji' => '❤️', 'word' => 'big if true'])
        ->assertOk();

    $html = $response->getContent();

    // The visible chip emoji spans, not a raw byte count -- the emoji legitimately also appears
    // inside the chip's own remove button's `hx-vals` JSON attribute (unescaped Unicode), which
    // Crawler never surfaces as element text since it isn't rendered content.
    $chipEmojis = new Crawler($html)->filter('.kop-rchip__emoji')->each(fn (Crawler $span) => trim($span->text()));

    expect($chipEmojis)->toBe(['❤️'])
        ->and($html)->not->toContain('React with ❤️')
        ->and($html)->toContain('big if true');
});

it('still shows a plain rail badge for a wordless reaction, alongside an unrelated worded chip on the same emoji', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada-worded-dup2@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);
    $wordless = Person::create(['name' => 'Cleo', 'email' => 'cleo-worded-dup2@example.test', 'password' => 'secret']);
    $worded = Person::create(['name' => 'Bob', 'email' => 'bob-worded-dup2@example.test', 'password' => 'secret']);

    $this->actingAs($wordless)->post("/_xhr/kopling-reactions/moment/{$moment->id}", ['emoji' => '❤️'])->assertOk();
    $response = $this->actingAs($worded)
        ->post("/_xhr/kopling-reactions/moment/{$moment->id}/word", ['emoji' => '❤️', 'word' => 'so true'])
        ->assertOk();

    $html = $response->getContent();
    $rail = new Crawler($html)->filter('#reactions-'.$moment->id);

    // The rail's own wordless pill for ❤️ (its emoji span, not the count badge's generic "1" --
    // scoping to the rail avoids matching an unrelated "1" appearing elsewhere) alongside the
    // unrelated worded chip.
    $railEmojis = $rail->filter('button span.text-sm.leading-none')->each(fn (Crawler $s) => trim($s->text()));

    expect($railEmojis)->toContain('❤️')
        ->and($rail->filter('.badge.tabular-nums')->text())->toBe('1')
        ->and($html)->toContain('so true');
});
