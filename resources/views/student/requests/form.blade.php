<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $supportRequest->exists ? __('Edit request') : __('New request') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <div class="mb-6 text-sm text-gray-600">
                    {{ __('Room :name', ['name' => $classroom->name]) }}
                </div>

                <form method="POST" action="{{ $action }}" class="space-y-5">
                    @csrf
                    @if ($method !== 'POST')
                        @method($method)
                    @endif

                    <div>
                        <x-input-label for="subject_id" :value="__('Subject')" />
                        <select id="subject_id" name="subject_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            <option value="">{{ __('Choose') }}</option>
                            @foreach ($subjects as $subject)
                                <option value="{{ $subject->id }}" @selected((int) old('subject_id', $supportRequest->subject_id) === $subject->id)>
                                    {{ $subject->name }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('subject_id')" class="mt-2" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="moodle_tile_number" :value="__('Moodle tile')" />
                            <x-text-input id="moodle_tile_number" name="moodle_tile_number" type="number" min="1" max="9999" value="{{ old('moodle_tile_number', $supportRequest->moodle_tile_number) }}" class="mt-1 block w-full" required />
                            <x-input-error :messages="$errors->get('moodle_tile_number')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="table_number" :value="__('Table number')" />
                            <x-text-input id="table_number" name="table_number" value="{{ old('table_number', $supportRequest->table_number) }}" class="mt-1 block w-full" required />
                            <x-input-error :messages="$errors->get('table_number')" class="mt-2" />
                        </div>
                    </div>

                    @if ($requestTypes->isNotEmpty())
                        @php
                            $selectedRequestTypeId = old('request_type_id');

                            if ($selectedRequestTypeId === null && $supportRequest->request_type) {
                                $selectedRequestTypeId = $requestTypes->firstWhere('name', $supportRequest->request_type)?->id;
                            }
                        @endphp

                        <div>
                            <x-input-label for="request_type_id" :value="__('Request type')" />
                            <select id="request_type_id" name="request_type_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('Choose') }}</option>
                                @foreach ($requestTypes as $requestType)
                                    <option value="{{ $requestType->id }}" @selected((string) $selectedRequestTypeId === (string) $requestType->id)>
                                        {{ $requestType->name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('request_type_id')" class="mt-2" />
                        </div>
                    @endif

                    <div>
                        <x-input-label for="comment" :value="__('Comment')" />
                        <textarea id="comment" name="comment" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('comment', $supportRequest->comment) }}</textarea>
                        <x-input-error :messages="$errors->get('comment')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <a href="{{ route('student.dashboard') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">
                            {{ __('Back') }}
                        </a>

                        <x-primary-button>
                            {{ $supportRequest->exists ? __('Save') : __('Create') }}
                        </x-primary-button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
