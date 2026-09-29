<div class="flex items-center gap-4">
    <x-k::person.avatar name="Jane Doe" color="#2b4a9b" />
    <x-k::person.avatar name="Ada Lovelace" color="#7c3aed" mask="mask-hexagon" size="w-10" />
    <x-k::person.avatar name="Guest" size="w-10" presence="online" />
    <x-k::person.avatar name="Away" color="#16295e" size="w-10" presence="offline" />
</div>
<div class="flex items-center gap-6 pt-4">
    <x-k::person.avatar name="Jane Doe" color="#2b4a9b" size="w-12">
        <x-slot:indicators>
            <span class="indicator-item badge badge-sm">23'</span>
        </x-slot:indicators>
    </x-k::person.avatar>
    <x-k::person.avatar name="Ada Lovelace" color="#7c3aed" size="w-12">
        <x-slot:indicators>
            <span class="indicator-item badge badge-sm badge-primary">9</span>
            <span class="indicator-item indicator-bottom status status-success"></span>
        </x-slot:indicators>
    </x-k::person.avatar>
</div>
