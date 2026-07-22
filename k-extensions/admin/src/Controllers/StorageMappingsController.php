<?php

declare(strict_types=1);

namespace Kopling\Admin\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Storage\Drive;
use Kopling\Core\Storage\StorageAccess;
use Kopling\Core\Storage\StorageMapping;
use Kopling\Core\Storage\StoragePermission;
use Kopling\Core\Storage\StorageRequest;

/**
 * Lists every declared `StorageRequest` (`Manager::storageDrivers()`) alongside its current
 * mapping, if any, plus any mapping row that no longer matches a declared request -- both
 * directions of the "stale" diff `storage_mappings.request_id` being a real column makes
 * possible, rather than a settings-key scan (see `.docs/planning/pages-docs-portal-plan.md`).
 *
 * `request_id`/`drive_id` travel in the POST body rather than as route-bound models -- a
 * request id already contains "::" (`kopling-docs::content`), and `StorageMapping`'s primary
 * key *is* that string, so binding it straight into a route segment would mean fighting URL
 * encoding for no real benefit over just reading it from the form.
 */
class StorageMappingsController
{
    public function index(Manager $manager): View
    {
        $declared = collect($manager->storageDrivers())->flatten(1);
        $mappings = StorageMapping::with('drive')->get()->keyBy('request_id');
        $drives = Drive::where('enabled', true)->orderBy('name')->get();

        $rows = $declared->map(fn (StorageRequest $request) => [
            'request' => $request,
            'mapping' => $mappings->get($request->id),
            'eligibleDrives' => $drives->filter(fn (Drive $drive) => $this->eligible($request, $drive))->values(),
        ]);

        $declaredIds = $declared->pluck('id');
        $orphaned = $mappings->reject(fn (StorageMapping $mapping) => $declaredIds->contains($mapping->request_id));

        return view('kopling-admin::storage.index', [
            'rows' => $rows,
            'orphaned' => $orphaned,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        StorageMapping::updateOrCreate(
            ['request_id' => $request->input('request_id')],
            ['drive_id' => $request->input('drive_id'), 'prefix' => $request->input('prefix') ?: null],
        );

        return redirect()->route('kopling-admin::admin/storage');
    }

    public function destroy(Request $request): RedirectResponse
    {
        StorageMapping::where('request_id', $request->input('request_id'))->delete();

        return redirect()->route('kopling-admin::admin/storage');
    }

    /**
     * `Private` access and `ReadOnly` permission never narrow the picker -- a private-access
     * request just means "no requirement to serve a public URL", not "refuses a drive that
     * could"; `ReadOnly` is enforced by `Resolver` regardless of the drive's own `writable` flag
     * (see `Resolver::resolve()` and the plan doc's note on why those two stay independent).
     */
    protected function eligible(StorageRequest $request, Drive $drive): bool
    {
        if ($request->access === StorageAccess::Public && ! $drive->supports_public) {
            return false;
        }

        if ($request->access === StorageAccess::Signed && ! $drive->supports_signed) {
            return false;
        }

        if ($request->permission === StoragePermission::ReadWrite && ! $drive->writable) {
            return false;
        }

        return true;
    }
}
