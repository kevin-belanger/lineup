@props(['supportRequest'])

@php
    $badgeBase = 'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold';
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-wrap gap-2']) }}>
    @if ($supportRequest->status === \App\Models\SupportRequest::STATUS_WAITING)
        <span class="{{ $badgeBase }} bg-amber-50 text-amber-800 ring-1 ring-amber-200">
            {{ __('En attente') }}
        </span>
    @endif

    @if ($supportRequest->assigned_teacher_id)
        <span class="{{ $badgeBase }} bg-indigo-50 text-indigo-800 ring-1 ring-indigo-200">
            @if ($supportRequest->assignedTeacher)
                {{ __('Pris en charge par :name', ['name' => $supportRequest->assignedTeacher->name]) }}
            @else
                {{ __('Pris en charge') }}
            @endif
        </span>
    @endif

    @if ($supportRequest->status === \App\Models\SupportRequest::STATUS_PAUSED)
        <span class="{{ $badgeBase }} bg-sky-50 text-sky-800 ring-1 ring-sky-200">
            {{ __('En pause') }}
        </span>
    @endif

    @if ($supportRequest->status === \App\Models\SupportRequest::STATUS_READY)
        <span class="{{ $badgeBase }} bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200">
            {{ __('Prêt à revoir') }}
        </span>
    @endif

    @if ($supportRequest->status === \App\Models\SupportRequest::STATUS_COMPLETED)
        <span class="{{ $badgeBase }} bg-green-50 text-green-800 ring-1 ring-green-200">
            {{ __('Terminée') }}
        </span>
    @endif

    @if ($supportRequest->status === \App\Models\SupportRequest::STATUS_CANCELLED)
        <span class="{{ $badgeBase }} bg-gray-100 text-gray-700 ring-1 ring-gray-200">
            {{ __('Annulée') }}
        </span>
    @endif
</div>
