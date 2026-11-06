<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Kopling\Core\People\Group;
use Kopling\Core\People\Person;
use Kopling\OAuth\Scope;
use Kopling\OAuth\Scopes;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use phpseclib4\Crypt\RSA;

function oauthTestKey(): RSA
{
    static $key;

    return $key ??= RSA::createKey(2048);
}

beforeEach(function () {
    $key = oauthTestKey();
    config()->set('passport.private_key', (string) $key);
    config()->set('passport.public_key', (string) $key->getPublicKey());

    Scopes::flush();
    Scopes::add(new Scope('test-extension::read', 'See your things'));
    Scopes::add(new Scope('test-extension::admin', 'Change site settings', 'kopling-core::manage-people'));

    Route::middleware('auth:api')->get('/__oauth-test/me', fn () => [
        'id' => request()->user()->id,
        'scopes' => request()->user()->token()->scopes,
    ]);
});

afterEach(fn () => Scopes::flush());

/**
 * Laravel's test client keeps guards (and their user) between requests; a real request never does.
 */
function bearer($test, string $token)
{
    app('auth')->forgetGuards();

    return $test->withToken($token);
}

function oauthPerson(array $permissions = []): Person
{
    $person = Person::create(['name' => 'Daan', 'email' => Str::random(8).'@example.test', 'password' => 'secret']);
    if ($permissions !== []) {
        $group = Group::create(['name' => 'Group '.Str::random(6)]);
        foreach ($permissions as $permission) {
            $group->givePermissionTo($permission);
        }
        $person->groups()->attach($group);
    }

    return $person;
}

function publicClient(): Client
{
    return app(ClientRepository::class)->createAuthorizationCodeGrantClient('Assistant', ['https://assistant.test/callback'], confidential: false);
}

/**
 * @return array{0: string, 1: array<string, string>}
 */
function authorizeQuery(Client $client, string $scope): array
{
    $verifier = Str::random(64);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    return [$verifier, [
        'client_id' => $client->id,
        'redirect_uri' => 'https://assistant.test/callback',
        'response_type' => 'code',
        'scope' => $scope,
        'state' => 'xyz',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]];
}

/**
 * Runs consent + code exchange as `$person`; returns the token response.
 */
function connect($test, Person $person, Client $client, string $scope): array
{
    [$verifier, $query] = authorizeQuery($client, $scope);
    $test->actingAs($person)->get('/oauth/authorize?'.http_build_query($query))->assertOk();
    $token = session('authToken');

    $redirect = $test->actingAs($person)->post('/oauth/authorize', ['auth_token' => $token, 'state' => 'xyz', 'client_id' => $client->id])
        ->assertRedirect()->headers->get('Location');
    parse_str((string) parse_url($redirect, PHP_URL_QUERY), $params);

    return $test->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->id,
        'redirect_uri' => 'https://assistant.test/callback',
        'code_verifier' => $verifier,
        'code' => $params['code'],
    ])->assertOk()->json();
}

it('sends a guest to the login page and back to the consent screen afterwards', function () {
    [, $query] = authorizeQuery(publicClient(), 'test-extension::read');
    $url = '/oauth/authorize?'.http_build_query($query);

    $this->get($url)->assertRedirect('/login');
    parse_str((string) parse_url(session('url.intended'), PHP_URL_QUERY), $intended);
    expect(strtok(session('url.intended'), '?'))->toBe(url('/oauth/authorize'))
        ->and($intended)->toEqual($query);
});

it('asks for consent in Kopling\'s own layout, marking what the person may not grant', function () {
    [, $query] = authorizeQuery(publicClient(), 'test-extension::read test-extension::admin');

    $this->actingAs(oauthPerson())->get('/oauth/authorize?'.http_build_query($query))
        ->assertOk()
        ->assertSee('Assistant wants access to your account')
        ->assertSee('See your things')
        ->assertSee('Change site settings')
        ->assertSee('not allowed for you')
        ->assertSee('After this you go back to <span class="font-mono font-semibold">assistant.test</span>', false);
});

it('issues a token through code and PKCE, without scopes the person may not grant', function () {
    $person = oauthPerson();
    $response = connect($this, $person, publicClient(), 'test-extension::read test-extension::admin');

    bearer($this, $response['access_token'])->getJson('/__oauth-test/me')
        ->assertOk()
        ->assertJson(['id' => $person->id, 'scopes' => ['test-extension::read']]);
});

it('grants a permission-bound scope to a person who has the permission', function () {
    $person = oauthPerson(['kopling-core::manage-people']);
    $response = connect($this, $person, publicClient(), 'test-extension::admin');

    bearer($this, $response['access_token'])->getJson('/__oauth-test/me')->assertJson(['scopes' => ['test-extension::admin']]);
});

it('redirects back with an error when the person denies', function () {
    $person = oauthPerson();
    $client = publicClient();
    [, $query] = authorizeQuery($client, 'test-extension::read');
    $this->actingAs($person)->get('/oauth/authorize?'.http_build_query($query));

    $this->actingAs($person)->delete('/oauth/authorize', ['auth_token' => session('authToken'), 'state' => 'xyz', 'client_id' => $client->id])
        ->assertRedirectContains('error=access_denied');
});

it('stops a token working once the person\'s access is blocked', function () {
    $person = oauthPerson();
    $response = connect($this, $person, publicClient(), 'test-extension::read');

    $person->forceFill(['access_blocked_at' => now()])->save();

    bearer($this, $response['access_token'])->getJson('/__oauth-test/me')->assertUnauthorized();
});

it('lists a connected app under settings and disconnects it there', function () {
    $person = oauthPerson();
    $client = publicClient();
    $response = connect($this, $person, $client, 'test-extension::read');

    $this->actingAs($person)->get('/settings')->assertSee('Connected apps')->assertSee('Assistant')->assertSee('See your things');
    $this->actingAs($person)->post("/settings/apps/{$client->id}/revoke")->assertRedirect();

    bearer($this, $response['access_token'])->getJson('/__oauth-test/me')->assertUnauthorized();
});

it('creates a personal token once, with only grantable scopes, and revokes it', function () {
    $person = oauthPerson();

    $this->actingAs($person)->post('/settings/apps/tokens', ['name' => 'CLI', 'scopes' => ['test-extension::admin']])->assertSessionHasErrors('scopes.0');
    $this->actingAs($person)->post('/settings/apps/tokens', ['name' => 'CLI', 'scopes' => ['test-extension::read']])->assertSessionHas('oauth_new_token');
    $plain = session('oauth_new_token');

    bearer($this, $plain)->getJson('/__oauth-test/me')->assertJson(['id' => $person->id, 'scopes' => ['test-extension::read']]);

    $token = Passport::token()->newQuery()->where('user_id', $person->id)->sole();
    $this->actingAs($person)->post("/settings/apps/tokens/{$token->id}/revoke")->assertRedirect();
    bearer($this, $plain)->getJson('/__oauth-test/me')->assertUnauthorized();
});

it('never lets someone revoke another person\'s token', function () {
    $owner = oauthPerson();
    $this->actingAs($owner)->post('/settings/apps/tokens', ['name' => 'CLI', 'scopes' => ['test-extension::read']]);
    $token = Passport::token()->newQuery()->sole();

    $this->actingAs(oauthPerson())->post("/settings/apps/tokens/{$token->id}/revoke")->assertNotFound();
    expect($token->fresh()->revoked)->toBeFalse();
});

it('hides the settings section when there is nothing to connect or manage', function () {
    Scopes::flush();

    $this->actingAs(oauthPerson())->get('/settings')->assertOk()->assertDontSee('Connected apps');
});

it('shows admins every app and token, and lets them revoke a client for everyone', function () {
    $admin = oauthPerson(['kopling-admin::access-admin', 'kopling-oauth::manage-oauth']);
    $person = oauthPerson();
    $client = publicClient();
    $response = connect($this, $person, $client, 'test-extension::read');

    $this->actingAs(oauthPerson(['kopling-admin::access-admin']))->get('/admin/oauth')->assertForbidden();
    $this->actingAs($admin)->get('/admin/oauth')->assertOk()->assertSee('Assistant')->assertSee('used by 1 person')->assertDontSee('no signing keys');

    $this->actingAs($admin)->post("/admin/oauth/clients/{$client->id}/revoke")->assertRedirect();
    expect($client->fresh()->revoked)->toBeTrue();
    bearer($this, $response['access_token'])->getJson('/__oauth-test/me')->assertUnauthorized();
});

it('warns admins when signing keys are missing and creates them', function () {
    $tmp = sys_get_temp_dir().'/kopling-oauth-'.Str::random(8);
    mkdir($tmp);
    $this->app->useStoragePath($tmp);
    config()->set('passport.private_key', null);
    config()->set('passport.public_key', null);
    $admin = oauthPerson(['kopling-admin::access-admin', 'kopling-oauth::manage-oauth']);

    $this->actingAs($admin)->get('/admin/oauth')->assertSee('no signing keys');
    $this->actingAs($admin)->post('/admin/oauth/keys')->assertRedirect();

    expect(is_file("$tmp/oauth-private.key"))->toBeTrue()
        ->and(decoct(fileperms("$tmp/oauth-private.key") & 0777))->toBe('600');
    $this->actingAs($admin)->post('/admin/oauth/keys')->assertStatus(422);
});

it('deletes a person\'s tokens with the person', function () {
    $person = oauthPerson();
    connect($this, $person, publicClient(), 'test-extension::read');

    $person->delete();

    expect(Passport::token()->newQuery()->count())->toBe(0);
});
