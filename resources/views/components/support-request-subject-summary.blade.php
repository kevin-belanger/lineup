@props(['supportRequest'])

@php
    $fieldSummary = $supportRequest->fieldAnswerSummary();
@endphp

{{ ($supportRequest->subject?->name ?? 'N/A').($fieldSummary !== '' ? ' - '.$fieldSummary : '') }}
