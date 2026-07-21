@props(['data' => []])

<input type="text" name="title" maxlength="150" required x-show="open" x-cloak
       placeholder="{{ __('kopling-composer::messages.title_placeholder') }}"
       class="input outline-none bg-transparent basis-full flex-1 min-w-0 sm:basis-auto">
