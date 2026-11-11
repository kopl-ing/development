<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Form;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * A first and last day picked in one calendar (Vanilla Calendar Pro), submitted as two plain
 * `Y-m-d` fields so a controller validates it like two date inputs, which is also what it renders
 * without JS. `marked` (`['Y-m-d' => 'label']`) flags days worth seeing while choosing.
 */
class DateRange extends Component
{
    public function __construct(public array $data = [])
    {
    }

    public function render(): View
    {
        return view('kopling-core::ux.form.date-range', [
            'label' => $this->data['label'] ?? null,
            'description' => $this->data['description'] ?? null,
            'startName' => $this->data['startName'] ?? 'starts_on',
            'endName' => $this->data['endName'] ?? 'ends_on',
            'start' => $this->data['start'] ?? '',
            'end' => $this->data['end'] ?? '',
            'startLabel' => $this->data['startLabel'] ?? __('kopling-core::ux.date_range_start'),
            'endLabel' => $this->data['endLabel'] ?? __('kopling-core::ux.date_range_end'),
            'required' => (bool) ($this->data['required'] ?? false),
            'min' => $this->data['min'] ?? null,
            'max' => $this->data['max'] ?? null,
            'marked' => $this->data['marked'] ?? [],
            'locale' => str_replace('_', '-', app()->getLocale()),
        ]);
    }
}
