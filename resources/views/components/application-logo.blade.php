@props(['alt' => app(\App\Services\ApplicationSettings::class)->displayName()])

<img
    src="{{ asset(app(\App\Services\ApplicationSettings::class)->logoPath()) }}"
    alt="{{ $alt }}"
    {{ $attributes }}
>
