<div class="hero bg-base-200 rounded-box py-16">
    <div class="hero-content text-center">
        <div class="max-w-md flex flex-col gap-4">
            <h1 class="text-4xl font-bold">{{ $page->title }}</h1>
            @if ($section->data['subtitle'] ?? null)
                <p class="text-lg opacity-80">{{ $section->data['subtitle'] }}</p>
            @endif
            @if ($section->data['cta_label'] ?? null)
                <a href="{{ $section->data['cta_url'] ?? '#' }}" class="btn btn-primary">{{ $section->data['cta_label'] }}</a>
            @endif
        </div>
    </div>
</div>
