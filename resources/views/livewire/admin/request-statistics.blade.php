@php
    $formatMinutes = fn ($value) => $value === null ? __('N/A') : $value.' min';
@endphp

<section class="space-y-6">
    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-4 border-b border-gray-100 pb-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">{{ __('Request statistics') }}</h3>
                <p class="mt-1 text-sm text-gray-600">{{ __('Completed requests grouped by completion date.') }}</p>
            </div>
        </div>

        <div class="grid gap-3 py-4 md:grid-cols-2 xl:grid-cols-[0.9fr_1.15fr_1.15fr]">
            <div>
                <x-input-label for="statistics-period" :value="__('Period')" />
                <select id="statistics-period" wire:model.live="period" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="today">{{ __('Today') }}</option>
                    <option value="last_30_days">{{ __('Last 30 days') }}</option>
                    <option value="month">{{ __('Month') }}</option>
                    <option value="custom">{{ __('Date or date range') }}</option>
                </select>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div @class(['hidden' => $period !== 'month'])>
                    <x-input-label for="statistics-month" :value="__('Month')" />
                    <input id="statistics-month" type="month" wire:model.live="month" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div @class(['hidden' => ! in_array($period, ['last_30_days', 'custom'], true)])>
                    <x-input-label for="statistics-start-date" :value="__('Start date')" />
                    <input id="statistics-start-date" type="date" wire:model.live="startDate" @disabled($period !== 'custom') class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-500">
                </div>

                <div @class(['hidden' => ! in_array($period, ['last_30_days', 'custom'], true)])>
                    <x-input-label for="statistics-end-date" :value="__('End date')" />
                    <input id="statistics-end-date" type="date" wire:model.live="endDate" @disabled($period !== 'custom') class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-500">
                </div>
            </div>

            <div>
                <x-input-label for="statistics-classrooms" :value="__('Rooms')" />
                <select id="statistics-classrooms" wire:model.live="classroomIds" multiple size="4" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach ($classrooms as $classroom)
                        <option value="{{ $classroom->id }}">{{ $classroom->name }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">{{ __('Leave empty to include all rooms.') }}</p>
            </div>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-sm font-medium text-gray-500">{{ __('Completed requests') }}</div>
            <div class="mt-2 text-3xl font-semibold text-gray-900">{{ $totalRequests }}</div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-sm font-medium text-gray-500">{{ __('Distinct students') }}</div>
            <div class="mt-2 text-3xl font-semibold text-gray-900">{{ $distinctStudents }}</div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-sm font-medium text-gray-500">{{ __('Average wait time') }}</div>
            <div class="mt-2 text-3xl font-semibold text-gray-900">{{ $formatMinutes($waitStats['average']) }}</div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-sm font-medium text-gray-500">{{ __('Average intervention time') }}</div>
            <div class="mt-2 text-3xl font-semibold text-gray-900">{{ $formatMinutes($interventionStats['average']) }}</div>
        </div>
    </div>

    <div class="grid gap-4">
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <h4 class="text-base font-semibold text-gray-900">{{ __('Wait time') }}</h4>
            <dl class="mt-4 grid grid-cols-3 gap-3 text-sm">
                <div>
                    <dt class="text-gray-500">{{ __('Average') }}</dt>
                    <dd class="mt-1 font-semibold text-gray-900">{{ $formatMinutes($waitStats['average']) }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">{{ __('Minimum') }}</dt>
                    <dd class="mt-1 font-semibold text-gray-900">{{ $formatMinutes($waitStats['min']) }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">{{ __('Maximum') }}</dt>
                    <dd class="mt-1 font-semibold text-gray-900">{{ $formatMinutes($waitStats['max']) }}</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <h4 class="text-base font-semibold text-gray-900">{{ __('Intervention time') }}</h4>
            <dl class="mt-4 grid grid-cols-3 gap-3 text-sm">
                <div>
                    <dt class="text-gray-500">{{ __('Average') }}</dt>
                    <dd class="mt-1 font-semibold text-gray-900">{{ $formatMinutes($interventionStats['average']) }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">{{ __('Minimum') }}</dt>
                    <dd class="mt-1 font-semibold text-gray-900">{{ $formatMinutes($interventionStats['min']) }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">{{ __('Maximum') }}</dt>
                    <dd class="mt-1 font-semibold text-gray-900">{{ $formatMinutes($interventionStats['max']) }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <div
        wire:key="request-statistics-charts-{{ $chartKey }}"
        x-data
        x-init="$nextTick(() => window.renderRequestStatisticsCharts && window.renderRequestStatisticsCharts($el))"
        data-statistics-charts
        data-chart='@json($chartData)'
        data-requests-label="{{ __('Requests') }}"
        data-students-label="{{ __('Distinct students') }}"
        data-wait-label="{{ __('Average wait') }}"
        data-intervention-label="{{ __('Average intervention') }}"
        data-count-axis-label="{{ __('Count') }}"
        data-minutes-axis-label="{{ __('Minutes') }}"
        class="grid gap-4"
    >
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <h4 class="text-base font-semibold text-gray-900">{{ __('Requests and students by day') }}</h4>
            <div class="mt-4 h-80">
                <canvas data-statistics-chart="counts"></canvas>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <h4 class="text-base font-semibold text-gray-900">{{ __('Average durations by day') }}</h4>
            <div class="mt-4 h-80">
                <canvas data-statistics-chart="durations"></canvas>
            </div>
        </div>
    </div>

    <div class="grid gap-4">
        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 p-4">
                <h4 class="text-base font-semibold text-gray-900">{{ __('Requests by subject') }}</h4>
                <p class="mt-1 text-sm text-gray-600">{{ __('Subjects linked to completed requests for the selected filters.') }}</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th scope="col" class="px-4 py-3">{{ __('Subject') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ __('Requests') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ __('Distinct students') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">%</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ __('Average wait') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ __('Average intervention') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($subjectRows as $row)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $row['subject_name'] }}</td>
                                <td class="px-4 py-3 text-right text-gray-700">{{ $row['requests'] }}</td>
                                <td class="px-4 py-3 text-right text-gray-700">{{ $row['distinct_students'] }}</td>
                                <td class="px-4 py-3 text-right text-gray-700">{{ $row['percent'] }}%</td>
                                <td class="px-4 py-3 text-right text-gray-700">{{ $formatMinutes($row['wait_average']) }}</td>
                                <td class="px-4 py-3 text-right text-gray-700">{{ $formatMinutes($row['intervention_average']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">{{ __('No requests match the filters.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <livewire:admin.request-tile-statistics
            :period="$period"
            :start-date="$startDate"
            :end-date="$endDate"
            :month="$month"
            :classroom-ids="$classroomIds"
            :key="'request-tile-statistics-'.$tileStatisticsKey"
        />
    </div>
</section>
