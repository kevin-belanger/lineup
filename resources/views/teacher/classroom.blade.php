<x-app-layout>
    <x-slot name="header">
        <x-teacher-breadcrumb />
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="space-y-6 p-6 text-gray-900">
                    <form method="POST" action="{{ route('teacher.classroom.update') }}" class="space-y-4">
                        @csrf
                        @method('PUT')

                        <div>
                            <x-input-label for="classroom_id" :value="__('Room')" />
                            <select id="classroom_id" name="classroom_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                <option value="">{{ __('Select a room') }}</option>
                                @foreach ($classrooms as $classroom)
                                    <option value="{{ $classroom->id }}" @selected((int) old('classroom_id', $currentClassroomId) === $classroom->id)>
                                        {{ $classroom->name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('classroom_id')" class="mt-2" />
                        </div>

                        @if ($activeRequests->isNotEmpty())
                            <div class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                                <p class="font-medium">{{ __('Change blocked while requests are active') }}</p>
                                <p class="mt-1">{{ __('Please complete, pause, or return requests to the queue before changing rooms.') }}</p>
                                <ul class="mt-3 space-y-1">
                                    @foreach ($activeRequests as $supportRequest)
                                        <li>
                                            {{ $supportRequest->classroom?->name ?? 'N/A' }} -
                                            <span class="inline-flex items-center gap-1">
                                                <span>{{ $supportRequest->subject?->name ?? 'N/A' }}</span>
                                                <x-subject-request-link :support-request="$supportRequest" />
                                            </span>
                                            -
                                            {{ $supportRequest->statusLabel() }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <x-primary-button>{{ __('Continue') }}</x-primary-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
