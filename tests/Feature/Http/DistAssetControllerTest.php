<?php

use Kopling\Core\Extension\Manager;

it('serves a compiled dist bundle and a sibling chunk Vite emitted alongside it through the same directory-scoped route', function () {
    $manager = app(Manager::class);
    $dir = rtrim($manager->path('kopling/core'), '/').'/dist';

    $editorUrl = Manager::distAssetUrl($dir, 'editor.js');
    $chunkUrl = Manager::distAssetUrl($dir, 'preload-helper.js');

    expect($chunkUrl)->toStartWith(Str::beforeLast($editorUrl, 'editor.js'));

    $this->get($editorUrl)->assertOk()->assertHeader('Content-Type', 'application/javascript');
    $this->get($chunkUrl)->assertOk()->assertHeader('Content-Type', 'application/javascript');
});

it('404s for a directory key nobody registered', function () {
    $this->get('/_kopling/assets/dist/not-a-real-key/editor.js')->assertNotFound();
});

it('404s for a filename that does not exist inside the registered directory', function () {
    $manager = app(Manager::class);
    $dir = rtrim($manager->path('kopling/core'), '/').'/dist';

    $this->get(Manager::distAssetUrl($dir, 'does-not-exist.js'))->assertNotFound();
});

it('404s for a path-traversal attempt through the filename segment', function () {
    $manager = app(Manager::class);
    $dir = rtrim($manager->path('kopling/core'), '/').'/dist';
    $key = Manager::distAssetUrl($dir, 'editor.js');
    $key = Str::between($key, '/dist/', '/editor.js');

    $this->get("/_kopling/assets/dist/{$key}/..%2f..%2fcomposer.json")->assertNotFound();
});
