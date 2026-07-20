<div {{ $attributes->merge(['class' => "card bg-base-100 outline -outline-offset-1 outline-base-content/10 {$classes}"]) }}>
    <x-k::card.badges :context="$context" :slot="$badgesSlot" />
    <div class="divide-y divide-base-content/10 overflow-hidden rounded-[inherit]">
        <x-k::card.top :context="$context" :slot="$topSlot" />
        <x-k::card.body :context="$context" :slot="$bodySlot" />
        <x-k::card.footer :context="$context" :slot="$footerSlot" />
    </div>
</div>
