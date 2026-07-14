<?php

declare(strict_types=1);

namespace Kopling\Demo\Command;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Kopling\Core\Extension\Manager;
use Kopling\Core\People\Group;
use Kopling\Core\People\Person;

/**
 * A quick way in: seeds (or resets) one admin@kopling.test account, in an "Administrators"
 * Group granted every permission any installed extension has registered -- not just
 * "kopling-admin::access-admin" -- so logging in as it gives full run of whatever's installed,
 * not a partial admin. Re-running always issues a fresh random password (never the same twice,
 * never stored anywhere but the (hashed) `people` row) and prints it once -- the only place it's
 * ever shown -- so losing it just means running this again, not a manual DB fix.
 */
class SeedAdminCommand extends Command
{
    protected $signature = 'kopling:demo:seed-admin';

    protected $description = 'Seed (or reset) an admin@kopling.test account with every registered permission';

    public function handle(Manager $manager): int
    {
        $password = Str::password(20, symbols: false);

        $person = Person::query()->firstOrNew(['email' => 'admin@kopling.test']);
        $person->name = $person->name ?? 'Admin';
        $person->password = $password;
        $person->save();

        $group = Group::query()->firstOrCreate(['name' => 'Administrators']);

        if (! $person->groups()->where('groups.id', $group->id)->exists()) {
            $person->groups()->attach($group);
        }

        foreach ($manager->permissions() as $permission) {
            $group->givePermissionTo($permission->id);
        }

        $this->components->info('Admin account ready.');
        $this->components->twoColumnDetail('Email', $person->email);
        $this->components->twoColumnDetail('Password', $password);
        $this->newLine();
        $this->line('  <fg=gray>Shown once, not stored anywhere in plain text -- re-run this command to reset it.</>');

        return self::SUCCESS;
    }
}
