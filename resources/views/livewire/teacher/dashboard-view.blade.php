<div>
    <livewire:teacher.request-change-watcher />

    <div class="mx-auto mb-6 flex max-w-7xl justify-end px-4 sm:px-6 lg:px-8">
        @if ($activeView === 'history')
            <x-secondary-button wire:key="teacher-dashboard-show-requests-button" type="button" wire:click="showRequests">
                {{ __('Retour aux demandes') }}
            </x-secondary-button>
        @else
            <x-secondary-button wire:key="teacher-dashboard-show-history-button" type="button" wire:click="showHistory">
                {{ __('Voir l’historique') }}
            </x-secondary-button>
        @endif
    </div>

    @if ($activeView === 'history')
        <div wire:key="teacher-dashboard-history-view" class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <livewire:teacher.request-history :key="'teacher-request-history'" />
        </div>
    @else
        <div wire:key="teacher-dashboard-requests-view" class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[minmax(360px,0.95fr)_minmax(0,1.05fr)] lg:items-start lg:px-8">
            <div class="space-y-5">
                <livewire:teacher.my-requests :key="'teacher-my-requests'" />
                <livewire:teacher.other-teacher-requests :key="'teacher-other-requests'" />
            </div>

            <div class="space-y-5">
                <livewire:teacher.waiting-queue :key="'teacher-waiting-queue'" />
            </div>
        </div>
    @endif
</div>
