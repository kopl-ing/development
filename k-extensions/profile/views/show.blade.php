@php use Kopling\Core\Ux\Context; @endphp
<x-k::community.chrome>
    <div id="profile">
        <div id="hero">

            <x-k::person.avatar size="w-40" :context="new Context(subject: $person)" />
        </div>

        <div role="tablist" class="tabs tabs-lift my-6">
            <label class="tab">
                <input type="radio" name="posts" checked="checked" />
                Posts
            </label>
            <div class="tab-content bg-base-100 border-base-300 p-6">

            </div>
        </div>
    </div>

</x-k::community.chrome>