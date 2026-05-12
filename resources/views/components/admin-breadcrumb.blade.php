@props(['current' => null])

<nav aria-label="{{ __('Breadcrumb') }}">
    <ol class="flex items-center gap-2 text-sm text-gray-500">
        <li>
            @if ($current)
                <a href="{{ route('admin.dashboard') }}" class="font-medium text-indigo-700 transition hover:text-indigo-900">
                    {{ __('Administration') }}
                </a>
            @else
                <span class="font-semibold text-gray-800">{{ __('Administration') }}</span>
            @endif
        </li>

        @if ($current)
            <li aria-hidden="true" class="text-gray-400">
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
            </li>
            <li class="font-semibold text-gray-800" aria-current="page">
                {{ $current }}
            </li>
        @endif
    </ol>
</nav>
