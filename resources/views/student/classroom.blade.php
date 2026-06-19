<x-app-layout>
    <x-slot name="header">
        <x-student-breadcrumb />
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <form
                    method="POST"
                    action="{{ route('student.classroom.update') }}"
                    class="space-y-4"
                    x-data="{ selectedClassroomId: @js((string) old('classroom_id', '')) }"
                >
                    @csrf
                    @method('PUT')

                    @if ($classrooms->isEmpty())
                        <p class="rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
                            {{ __('No room is currently available.') }}
                        </p>
                    @else
                        <div>
                            <x-input-label :value="__('Choose a room')" />

                            <div class="mt-2 space-y-2">
                                @foreach ($classrooms as $classroom)
                                    @php
                                        $selected = (int) old('classroom_id', $currentClassroomId) === $classroom->id;
                                    @endphp

                                    <div class="relative rounded-md border border-gray-200 bg-white shadow-sm transition hover:border-indigo-200 hover:bg-indigo-50/40 hover:shadow-sm">
                                        <input
                                            id="classroom_id_{{ $classroom->id }}"
                                            type="radio"
                                            name="classroom_id"
                                            value="{{ $classroom->id }}"
                                            class="peer sr-only"
                                            required
                                            @checked($selected)
                                            x-model="selectedClassroomId"
                                            x-on:change="$root.requestSubmit()"
                                        >

                                        <label
                                            for="classroom_id_{{ $classroom->id }}"
                                            class="flex cursor-pointer items-center justify-between gap-3 rounded-md px-4 py-3 pr-12 ring-inset transition peer-checked:ring-2 peer-checked:ring-indigo-500"
                                        >
                                            <span class="min-w-0">
                                                <span class="block truncate text-sm font-medium text-gray-900">{{ $classroom->name }}</span>
                                                @if ($classroom->description)
                                                    <span class="mt-0.5 block truncate text-xs text-gray-500">{{ $classroom->description }}</span>
                                                @endif
                                            </span>

                                            <span class="h-2 w-2 shrink-0 rounded-full bg-indigo-600 opacity-0 transition peer-checked:opacity-100"></span>
                                        </label>

                                        <span class="absolute right-3 top-1/2 z-10 -translate-y-1/2">
                                            <x-classroom-opening-status :classroom="$classroom" />
                                        </span>
                                    </div>
                                @endforeach
                            </div>

                            <x-input-error :messages="$errors->get('classroom_id')" class="mt-2" />
                        </div>
                    @endif
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
