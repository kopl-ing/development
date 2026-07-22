@php
    use Kopling\Core\Content\Moment;
    use Kopling\Core\Ux\Context;
@endphp
<x-k::page.pagination :context="new Context(subject: Moment::query()->latest())" />
