{{--
    Shown by LatestMomentsController instead of the poller (poll.blade.php) once something
    newer than `since` exists. Clicking loads the new moments and swaps this back to a
    resumed poller (see LatestMomentsController::load).
--}}
<div
    hx-get="{{ route($portal->id.'/moments.load', ['since' => $since]) }}"
    hx-trigger="click"
    hx-swap="outerHTML"
    class="btn btn-sm w-full mb-4"
>
    {{ $count }} new moment{{ $count === 1 ? '' : 's' }} — click to view
</div>
