<?php

declare(strict_types=1);

it('renders without an indicator wrapper by default', function () {
    $this->blade('<x-k::person.avatar name="Jane Doe" />')
        ->assertSee('avatar-placeholder', false)
        ->assertDontSee('class="indicator"', false);
});

it('wraps the avatar in a daisyUI indicator when indicators are passed', function () {
    $this->blade('<x-k::person.avatar name="Jane Doe"><x-slot:indicators><span class="indicator-item badge">23\'</span></x-slot:indicators></x-k::person.avatar>')
        ->assertSeeInOrder(['class="indicator"', 'indicator-item badge', "23'", 'avatar-placeholder'], false);
});
