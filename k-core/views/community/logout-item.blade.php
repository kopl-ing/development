<li>
    <form method="POST" action="{{ route('kopling-core::community/logout') }}">
        @csrf
        <button type="submit" class="contents">
            <x-k::icon name="kopling-core::logout" />
            {{ __('kopling-core::community.logout') }}
        </button>
    </form>
</li>
