@php
    $formatMinutes = fn ($value) => $value === null ? __('N/A') : $value.' min';
@endphp

<div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
    <div class="border-b border-gray-100 p-4">
        <h4 class="text-base font-semibold text-gray-900">{{ __('Requests by Moodle tile') }}</h4>
        <p class="mt-1 text-sm text-gray-600">{{ __('Choose a subject to see which tiles are linked to requests.') }}</p>

        <div class="mt-4">
            <x-input-label for="statistics-subject" :value="__('Subject')" />
            <select id="statistics-subject" wire:model.live="selectedSubjectId" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">{{ __('Choose') }}</option>
                @foreach ($subjectOptions as $subject)
                    @if ($subject['subject_id'] !== null)
                        <option value="{{ $subject['subject_id'] }}">{{ $subject['subject_name'] }}</option>
                    @endif
                @endforeach
            </select>
        </div>
    </div>

    @if ($selectedSubjectName)
        <div class="border-b border-gray-100 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700">
            {{ __('Subject') }}: {{ $selectedSubjectName }}
        </div>
    @endif

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <tr>
                    <th scope="col" class="px-4 py-3">{{ __('Moodle tile') }}</th>
                    <th scope="col" class="px-4 py-3 text-right">{{ __('Requests') }}</th>
                    <th scope="col" class="px-4 py-3 text-right">{{ __('Distinct students') }}</th>
                    <th scope="col" class="px-4 py-3 text-right">%</th>
                    <th scope="col" class="px-4 py-3 text-right">{{ __('Average wait') }}</th>
                    <th scope="col" class="px-4 py-3 text-right">{{ __('Average intervention') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($tileRows as $row)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $row['tile'] }}</td>
                        <td class="px-4 py-3 text-right text-gray-700">{{ $row['requests'] }}</td>
                        <td class="px-4 py-3 text-right text-gray-700">{{ $row['distinct_students'] }}</td>
                        <td class="px-4 py-3 text-right text-gray-700">{{ $row['percent'] }}%</td>
                        <td class="px-4 py-3 text-right text-gray-700">{{ $formatMinutes($row['wait_average']) }}</td>
                        <td class="px-4 py-3 text-right text-gray-700">{{ $formatMinutes($row['intervention_average']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">
                            {{ $selectedSubjectId === '' ? __('Choose a subject to see tile statistics.') : __('No requests match the filters.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
