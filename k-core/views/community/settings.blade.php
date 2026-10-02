@php use Kopling\Core\Http\Controllers\AccountSettingsController; use Kopling\Core\Ux\Context; @endphp
<x-k::community.chrome>
    <div class="max-w-2xl flex flex-col gap-6">
        <h1 class="text-2xl font-bold">{{ __('kopling-core::community.settings') }}</h1>

        <section class="card card-border bg-base-100">
            <form method="POST" action="{{ route('kopling-core::community/settings.update') }}" class="card-body gap-4">
                @csrf
                <h2 class="card-title">{{ __('kopling-core::community.account') }}</h2>
                <x-k::form.input :data="['name' => 'name', 'label' => __('kopling-core::community.name'), 'value' => old('name', $person->name)]" />
                @error('name')
                    <p class="text-error text-sm">{{ $message }}</p>
                @enderror
                @if (session('status'))
                    <p class="text-success text-sm">{{ session('status') }}</p>
                @endif
                <button type="submit" class="btn btn-primary self-start">{{ __('kopling-core::community.save') }}</button>
            </form>
        </section>

        <x-k::portal.slot :name="AccountSettingsController::SECTIONS_SLOT" :context="new Context(subject: $person)" />
    </div>
</x-k::community.chrome>
