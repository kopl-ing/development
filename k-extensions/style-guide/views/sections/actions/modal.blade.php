<x-k::modal label="Example modal">
    <x-slot:trigger>Modal</x-slot:trigger>
    <form method="dialog" class="flex flex-col gap-4">
        <p>Modal body content goes here.</p>
        <div class="flex gap-2">
            <button type="submit" class="btn btn-primary">Save</button>
            <x-k::modal.cancel />
        </div>
    </form>
</x-k::modal>
