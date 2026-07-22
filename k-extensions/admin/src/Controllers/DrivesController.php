<?php

declare(strict_types=1);

namespace Kopling\Admin\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Kopling\Core\Storage\Drive;

/**
 * Plain create/edit/delete for `Drive`s -- the registered storage backends `StorageMappingsController`
 * assigns declared `StorageRequest`s to. `driver` is a closed set (`local`/`s3`) for v1, matching
 * the storage resolver's own v1 scope (`.docs/planning/pages-docs-portal-plan.md`).
 */
class DrivesController
{
    public function index(): View
    {
        return view('kopling-admin::drives.index', [
            'drives' => Drive::withCount('mappings')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Drive::create($this->validated($request));

        return redirect()->route('kopling-admin::admin/drives');
    }

    public function update(Request $request, Drive $drive): RedirectResponse
    {
        $drive->update($this->validated($request));

        return redirect()->route('kopling-admin::admin/drives');
    }

    /**
     * `storage_mappings.drive_id` is `restrictOnDelete()` -- a drive still referenced by a
     * mapping throws straight from the DB rather than silently orphaning the mapping. Caught
     * here into a normal validation-style redirect instead of a raw 500.
     */
    public function destroy(Drive $drive): RedirectResponse
    {
        try {
            $drive->delete();
        } catch (QueryException) {
            return back()->withErrors(['drive' => __('kopling-admin::messages.drive_in_use')]);
        }

        return redirect()->route('kopling-admin::admin/drives');
    }

    /**
     * @return array{name: string, driver: string, settings: array, supports_public: bool, supports_signed: bool, writable: bool, enabled: bool}
     */
    protected function validated(Request $request): array
    {
        $settings = json_decode((string) $request->input('settings', '{}'), true);

        if (! is_array($settings)) {
            throw ValidationException::withMessages([
                'settings' => __('kopling-admin::messages.invalid_json', ['error' => json_last_error_msg()]),
            ]);
        }

        return [
            'name' => $request->input('name'),
            'driver' => $request->input('driver'),
            'settings' => $settings,
            'supports_public' => $request->boolean('supports_public'),
            'supports_signed' => $request->boolean('supports_signed'),
            'writable' => $request->boolean('writable'),
            'enabled' => $request->boolean('enabled', true),
        ];
    }
}
