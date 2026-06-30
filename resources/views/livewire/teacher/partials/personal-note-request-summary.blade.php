@props(['supportRequest'])

@php
    $courseUrlSettings = app(\App\Services\ApplicationSettings::class);
    $courseUrlTarget = $courseUrlSettings->courseUrlTarget();
    $courseUrlRel = $courseUrlSettings->courseUrlRel();
    $subjectUrl = $supportRequest->subjectUrl();
    $isPriority = $supportRequest->is_priority;
    $cardClass = $isPriority
        ? 'border-rose-200 bg-rose-50 ring-1 ring-rose-100'
        : 'border-gray-200 bg-white';
@endphp

<div class="rounded-md border {{ $cardClass }} p-3 shadow-sm">
    @if ($supportRequest->is_priority)
        <div class="min-w-0 space-y-2">
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide text-rose-800">{{ __('Priority') }}</span>
                <span class="font-semibold text-gray-950">{{ __('Sent by') }} {{ $supportRequest->priorityRequesterDisplayName() }}</span>
            </div>

            @if ($supportRequest->classroom)
                <div class="inline-flex rounded-full bg-white/70 px-2.5 py-0.5 text-sm font-medium text-gray-700 ring-1 ring-rose-100">{{ $supportRequest->classroom->name }}</div>
            @endif
        </div>
    @else
        <div class="flex flex-col gap-2">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 text-base font-semibold text-gray-950">
                    {{ $supportRequest->studentDisplayName() }}
                </div>

                @if ($supportRequest->shouldShowTableNumber())
                    <div class="inline-flex shrink-0 items-center justify-end gap-1.5 rounded-md bg-gray-50 px-2.5 py-1 text-sm font-semibold text-gray-800 ring-1 ring-gray-200" title="{{ __('Table') }} {{ $supportRequest->table_number }}">
                        <svg class="h-4 w-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25h6m-7.5 3h9m-12-6.75h15A1.5 1.5 0 0 0 21 12V5.25a1.5 1.5 0 0 0-1.5-1.5h-15A1.5 1.5 0 0 0 3 5.25V12a1.5 1.5 0 0 0 1.5 1.5Z" />
                        </svg>
                        <span>{{ $supportRequest->table_number }}</span>
                    </div>
                @endif
            </div>

            @if ($subjectUrl)
                <a
                    href="{{ $subjectUrl }}"
                    target="{{ $courseUrlTarget }}"
                    @if ($courseUrlRel) rel="{{ $courseUrlRel }}" @endif
                    class="-mx-2 block rounded-md px-2 py-1.5 text-sm font-medium leading-snug text-gray-700 transition hover:bg-indigo-50 hover:text-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    aria-label="{{ __('Open the subject link') }}"
                    title="{{ __('Open the subject link') }}"
                >
                    <span class="whitespace-normal break-words">
                        <x-support-request-subject-summary :support-request="$supportRequest" />
                        <svg class="ml-1 inline-block h-3.5 w-3.5 shrink-0 align-[-2px] text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 6.364 6.364l-1.768 1.768a4.5 4.5 0 0 1-6.364 0m1.768-8.132-1.768-1.768a4.5 4.5 0 0 0-6.364 0L3.29 8.688a4.5 4.5 0 0 0 6.364 6.364l1.768-1.768" />
                        </svg>
                    </span>
                </a>
            @else
                <div class="-mx-2 whitespace-normal break-words px-2 py-1.5 text-sm font-medium leading-snug text-gray-700">
                    <x-support-request-subject-summary :support-request="$supportRequest" />
                </div>
            @endif

            <div class="flex flex-wrap items-center gap-2 text-sm text-gray-700">
                @if ($supportRequest->classroom)
                    <span class="rounded-full bg-gray-50 px-2.5 py-0.5 font-medium text-gray-700 ring-1 ring-gray-200">{{ $supportRequest->classroom->name }}</span>
                @endif
                @if ($supportRequest->typeLabel() !== '')
                    <span class="rounded-full bg-white px-2.5 py-0.5 font-medium text-indigo-700 ring-1 ring-indigo-100">{{ $supportRequest->typeLabel() }}</span>
                @endif
            </div>
        </div>
    @endif
</div>
