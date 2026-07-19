<?php

declare(strict_types=1);

use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;
use Kopling\Core\Ux\Context;
use Kopling\Discussions\Reply;

function momentForTeaser(): Moment
{
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);

    return Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);
}

it('renders no avatar row and the empty-state text for a moment with no replies', function () {
    $moment = momentForTeaser();

    $html = (string) $this->blade('<x-dynamic-component :component="$component" :context="$context" />', [
        'component' => 'kopling-discussions::teaser',
        'context' => new Context(subject: $moment),
    ]);

    expect($html)->toContain('No replies yet')
        ->and($html)->not->toContain('avatar-group');
});

it('renders an avatar per distinct replier alongside the pluralized teaser text', function () {
    $moment = momentForTeaser();

    $bob = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);
    $cleo = Person::create(['name' => 'Cleo', 'email' => 'cleo@example.test', 'password' => 'secret']);

    Reply::create(['moment_id' => $moment->id, 'person_id' => $bob->id, 'body' => 'Nice one', 'body_html' => '<p>Nice one</p>']);
    Reply::create(['moment_id' => $moment->id, 'person_id' => $cleo->id, 'body' => 'Agreed here', 'body_html' => '<p>Agreed here</p>']);

    $html = (string) $this->blade('<x-dynamic-component :component="$component" :context="$context" />', [
        'component' => 'kopling-discussions::teaser',
        'context' => new Context(subject: $moment),
    ]);

    expect($html)->toContain('avatar-group')
        ->and(substr_count($html, 'avatar-placeholder'))->toBe(2)
        ->and($html)->toContain('>B<') // Bob's initial (single-word name -> one letter)
        ->and($html)->toContain('>C<') // Cleo's initial
        ->and($html)->toContain('2 people used');
});
