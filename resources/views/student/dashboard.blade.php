<x-app-layout>
    <x-slot name="header">
        <x-student-breadcrumb :classroom-name="$currentClassroom?->name" />
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="text-sm font-medium text-gray-500">{{ __('Current room') }}</div>
                        <div class="mt-1 flex items-center gap-1.5 text-lg font-semibold text-gray-900">
                            <span>{{ $currentClassroom?->name ?? __('No room selected') }}</span>
                            @if ($currentClassroom)
                                <x-classroom-opening-status :classroom="$currentClassroom" live show-closed-until />
                            @endif
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @if ($activeRequests->isEmpty())
                            <form method="POST" action="{{ route('student.classroom.leave') }}">
                                @csrf
                                <button type="submit" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50">
                                    {{ __('Change room') }}
                                </button>
                            </form>
                        @elseif ($hasTeacherHandledRequest)
                            <button
                                type="button"
                                x-data
                                x-on:click="$dispatch('open-modal', 'student-cannot-leave-classroom')"
                                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50"
                            >
                                {{ __('Change room') }}
                            </button>
                        @else
                            <button
                                type="button"
                                x-data
                                x-on:click="$dispatch('open-modal', 'student-confirm-leave-classroom')"
                                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50"
                            >
                                {{ __('Change room') }}
                            </button>
                        @endif

                        @if ($currentClassroom)
                            <a href="{{ route('student.requests.create') }}" class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700">
                                {{ __('New request') }}
                            </a>
                        @endif
                    </div>
                </div>
            </section>

            <livewire:student.active-requests />
        </div>
    </div>

    <x-modal name="student-confirm-leave-classroom" maxWidth="md" focusable>
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900">{{ __('Change room') }}</h3>
            <p class="mt-2 text-sm text-gray-600">
                {{ __('Do you want to cancel your active request and change rooms?') }}
            </p>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button type="button" x-on:click="$dispatch('close')">
                    {{ __('No') }}
                </x-secondary-button>

                <form method="POST" action="{{ route('student.classroom.leave') }}">
                    @csrf
                    <x-danger-button type="submit">
                        {{ __('Yes') }}
                    </x-danger-button>
                </form>
            </div>
        </div>
    </x-modal>

    <x-modal name="student-cannot-leave-classroom" maxWidth="md" focusable>
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900">{{ __('Change room') }}</h3>
            <p class="mt-2 text-sm text-gray-600">
                {{ __('You cannot leave this room because a request is being handled by a teacher.') }}
            </p>

            <div class="mt-6 flex justify-end">
                <x-secondary-button type="button" x-on:click="$dispatch('close')">
                    {{ __('Close') }}
                </x-secondary-button>
            </div>
        </div>
    </x-modal>
</x-app-layout>
