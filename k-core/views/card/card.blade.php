<div {{ $attributes->merge(['class' => "card card-border bg-base-100 {$classes}"]) }}>
    <div class="card-body">
        <x-k::card.top :context="$context" :slot="$topSlot" />
        <x-k::card.body :context="$context" :slot="$bodySlot" />
        <x-k::card.footer :context="$context" :slot="$footerSlot" />
    </div>
</div>
