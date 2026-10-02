<?php

declare(strict_types=1);

use Kopling\Core\People\Person;
use Kopling\SportsManagement\Team;
use Kopling\SportsManagement\TeamInvitation;

it('invites by email without revealing whether an account exists', function () {
    $owner = coach('Owner', 'owner@example.test');
    $team = staffedTeam($owner);
    coach('Known', 'known@example.test');

    $known = $this->actingAs($owner)->post("/sports-management/{$team->id}/invitations", ['email' => 'Known@example.test']);
    $unknown = $this->actingAs($owner)->post("/sports-management/{$team->id}/invitations", ['email' => 'nobody@example.test']);

    $known->assertRedirect()->assertSessionHasNoErrors();
    $unknown->assertRedirect()->assertSessionHasNoErrors();
    expect($team->invitations()->orderBy('email')->pluck('email')->all())->toBe(['known@example.test', 'nobody@example.test'])
        ->and($team->staff()->count())->toBe(1);
});

it('adds an invited account as staff only once it accepts', function () {
    $owner = coach('Owner', 'owner@example.test');
    $invitee = coach('Invitee', 'invitee@example.test');
    $team = staffedTeam($owner);
    $this->actingAs($owner)->post("/sports-management/{$team->id}/invitations", ['email' => 'invitee@example.test']);
    $invitation = TeamInvitation::firstOrFail();

    expect($team->isStaffedBy($invitee))->toBeFalse();
    $this->actingAs($invitee)->get('/sports-management')->assertSee(__('kopling-sports-management::messages.invitations'))->assertSee('JO11-2');

    $this->actingAs($invitee)->post("/sports-management/invitations/{$invitation->id}/accept")->assertRedirect();

    expect($team->isStaffedBy($invitee))->toBeTrue()
        ->and($team->isOwnedBy($invitee))->toBeFalse()
        ->and(TeamInvitation::count())->toBe(0);
});

it('refuses an invitation to anyone but the invited email', function () {
    $owner = coach('Owner', 'owner@example.test');
    $other = coach('Other', 'other@example.test');
    $team = staffedTeam($owner);
    $invitation = $team->invitations()->create(['email' => 'invitee@example.test']);

    $this->actingAs($other)->post("/sports-management/invitations/{$invitation->id}/accept")->assertNotFound();
    $this->actingAs($other)->post("/sports-management/invitations/{$invitation->id}/decline")->assertNotFound();

    expect($team->isStaffedBy($other))->toBeFalse()->and(TeamInvitation::count())->toBe(1);
});

it('lets staff revoke and the invitee decline an invitation', function () {
    $owner = coach('Owner', 'owner@example.test');
    $invitee = coach('Invitee', 'invitee@example.test');
    $team = staffedTeam($owner);
    $revoked = $team->invitations()->create(['email' => 'someone@example.test']);
    $declined = $team->invitations()->create(['email' => 'invitee@example.test']);

    $this->actingAs($owner)->post("/sports-management/{$team->id}/invitations/{$revoked->id}/delete")->assertRedirect();
    $this->actingAs($invitee)->post("/sports-management/invitations/{$declined->id}/decline")->assertRedirect('/sports-management');

    expect(TeamInvitation::count())->toBe(0)->and($team->isStaffedBy($invitee))->toBeFalse();
});

it('lets an owner remove other staff, but never another owner', function () {
    $owner = coach('Owner', 'owner@example.test');
    $staff = coach('Staff', 'staff@example.test');
    $coOwner = coach('Co-owner', 'co-owner@example.test');
    $team = staffedTeam($owner);
    $team->staff()->attach($staff);
    $team->staff()->attach($coOwner, ['owner' => true]);

    $this->actingAs($owner)->post("/sports-management/{$team->id}/staff/{$coOwner->id}/remove")->assertForbidden();
    $this->actingAs($owner)->post("/sports-management/{$team->id}/staff/{$staff->id}/remove")->assertRedirect();

    expect($team->isStaffedBy($staff))->toBeFalse()->and($team->isStaffedBy($coOwner))->toBeTrue();
});

it('forbids staff who are not an owner from removing anyone else', function () {
    $owner = coach('Owner', 'owner@example.test');
    $staff = coach('Staff', 'staff@example.test');
    $other = coach('Other', 'other@example.test');
    $team = staffedTeam($owner);
    $team->staff()->attach([$staff->id, $other->id]);

    $this->actingAs($staff)->post("/sports-management/{$team->id}/staff/{$owner->id}/remove")->assertForbidden();
    $this->actingAs($staff)->post("/sports-management/{$team->id}/staff/{$other->id}/remove")->assertForbidden();

    expect($team->staff()->count())->toBe(3);
});

it('lets anyone leave, except the last owner', function () {
    $owner = coach('Owner', 'owner@example.test');
    $staff = coach('Staff', 'staff@example.test', ['access-sports-management', 'track-matches']);
    $team = staffedTeam($owner);
    $team->staff()->attach($staff);

    $this->actingAs($owner)->post("/sports-management/{$team->id}/staff/{$owner->id}/remove")->assertSessionHasErrors('staff');
    $this->actingAs($staff)->post("/sports-management/{$team->id}/staff/{$staff->id}/remove")->assertRedirect('/sports-management');

    expect($team->isStaffedBy($owner))->toBeTrue()->and($team->isStaffedBy($staff))->toBeFalse();
});

it('lets an owner make another staff member an owner, then leave', function () {
    $owner = coach('Owner', 'owner@example.test');
    $staff = coach('Staff', 'staff@example.test');
    $team = staffedTeam($owner);
    $team->staff()->attach($staff);

    $this->actingAs($staff)->post("/sports-management/{$team->id}/staff/{$staff->id}/owner")->assertForbidden();
    $this->actingAs($owner)->post("/sports-management/{$team->id}/staff/{$staff->id}/owner")->assertRedirect();
    $this->actingAs($owner)->post("/sports-management/{$team->id}/staff/{$owner->id}/remove")->assertRedirect('/sports-management');

    expect($team->isOwnedBy($staff))->toBeTrue()->and($team->isStaffedBy($owner))->toBeFalse();
});

it('gives roster members no public profile page', function () {
    $team = staffedTeam(coach());
    $player = rosterMember($team, 'Jip');
    $member = Person::create(['name' => 'Community member', 'email' => 'member@example.test', 'password' => 'secret']);

    $this->get("/p/{$player->person_id}")->assertNotFound();
    $this->get("/p/{$member->id}")->assertOk();
});

it('keeps a hidden team away from its own staff', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $team->delete();

    $this->actingAs($coach)->get("/sports-management/{$team->id}")->assertNotFound();
    $this->actingAs($coach)->get('/sports-management')->assertDontSee('JO11-2');
});
