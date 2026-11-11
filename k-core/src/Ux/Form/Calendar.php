<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Form;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Days picked in one calendar (Vanilla Calendar Pro). `mode` `range` submits a first and last day
 * as two `Y-m-d` fields; `multiple` submits loose days as `{name}[]`, or as `{name}[i][date]` and
 * `{name}[i][time]` with `time`. Without JS it renders those same fields as plain inputs.
 * `marked` (`['Y-m-d' => 'label']`) flags days worth seeing while choosing.
 */
class Calendar extends Component
{
    public function __construct(public array $data = [])
    {
    }

    public function render(): View
    {
        $mode = ($this->data['mode'] ?? 'range') === 'multiple' ? 'multiple' : 'range';
        $time = $mode === 'multiple' && (bool) ($this->data['time'] ?? false);

        return view('kopling-core::ux.form.calendar', [
            'mode' => $mode,
            'label' => $this->data['label'] ?? null,
            'description' => $this->data['description'] ?? null,
            'required' => (bool) ($this->data['required'] ?? false),
            'min' => $this->data['min'] ?? null,
            'max' => $this->data['max'] ?? null,
            'marked' => $this->data['marked'] ?? [],
            'locale' => str_replace('_', '-', app()->getLocale()),
            'startName' => $this->data['startName'] ?? 'starts_on',
            'endName' => $this->data['endName'] ?? 'ends_on',
            'start' => $this->data['start'] ?? '',
            'end' => $this->data['end'] ?? '',
            'startLabel' => $this->data['startLabel'] ?? __('kopling-core::ux.calendar_start'),
            'endLabel' => $this->data['endLabel'] ?? __('kopling-core::ux.calendar_end'),
            'name' => $this->data['name'] ?? 'dates',
            'time' => $time,
            'rows' => $mode === 'multiple' ? $this->rows($time) : [],
            'dateLabel' => $this->data['dateLabel'] ?? __('kopling-core::ux.calendar_date'),
            'timeLabel' => $this->data['timeLabel'] ?? __('kopling-core::ux.calendar_time'),
        ]);
    }

    /**
     * @return array<int, array{date: string, time: string}>
     */
    protected function rows(bool $time): array
    {
        $rows = collect($this->data['value'] ?? [])
            ->map(fn ($row) => is_array($row)
                ? ['date' => (string) ($row['date'] ?? ''), 'time' => $time ? (string) ($row['time'] ?? '') : '']
                : ['date' => (string) $row, 'time' => ''])
            ->filter(fn (array $row) => $row['date'] !== '')
            ->sortBy('date')
            ->values()
            ->all();

        return [...$rows, ['date' => '', 'time' => '']];
    }
}
