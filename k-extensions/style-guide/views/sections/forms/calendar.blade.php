{{-- `range` submits two Y-m-d fields, `multiple` a list of days (with a time each when `time` is on). `marked` puts a dot on days worth seeing. --}}
<x-k::form.calendar :data="[
    'label' => 'Calendar',
    'description' => 'A first and last day picked in one calendar.',
    'startName' => 'example_starts_on',
    'endName' => 'example_ends_on',
    'start' => now()->addDays(7)->toDateString(),
    'end' => now()->addDays(11)->toDateString(),
    'marked' => [now()->addDays(9)->toDateString() => 'Something planned'],
]" />
<x-k::form.calendar :data="[
    'label' => 'Calendar, multiple',
    'description' => 'Loose days, each with an optional time.',
    'mode' => 'multiple',
    'name' => 'example_dates',
    'time' => true,
    'value' => [['date' => now()->addDays(3)->toDateString(), 'time' => '10:00']],
]" />
