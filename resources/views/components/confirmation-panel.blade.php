@props([
    'title',
    'message',
])

<div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
    <h3 class="text-lg font-semibold text-gray-900">{{ $title }}</h3>
    <p class="mt-3 text-sm text-gray-600">{{ $message }}</p>

    <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
        {{ $actions }}
    </div>
</div>
