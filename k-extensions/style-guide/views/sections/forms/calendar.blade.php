{{-- Submits two plain Y-m-d fields; `marked` puts a dot on days worth seeing while choosing. --}}
<x-k::form.date-range :data="[
    'label' => 'DateRange',
    'description' => 'A first and last day picked in one calendar.',
    'startName' => 'example_starts_on',
    'endName' => 'example_ends_on',
    'start' => now()->addDays(7)->toDateString(),
    'end' => now()->addDays(11)->toDateString(),
    'marked' => [now()->addDays(9)->toDateString() => 'Something planned'],
]" />
