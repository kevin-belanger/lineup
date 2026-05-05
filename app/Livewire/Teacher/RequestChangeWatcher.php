<?php

namespace App\Livewire\Teacher;

use App\Services\SupportRequestChangeMarker;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class RequestChangeWatcher extends Component
{
    public int $version = 0;

    public function mount(SupportRequestChangeMarker $changeMarker): void
    {
        $this->version = $changeMarker->current($this->currentClassroomId());
    }

    public function check(SupportRequestChangeMarker $changeMarker): void
    {
        $currentVersion = $changeMarker->current($this->currentClassroomId());

        if ($currentVersion === $this->version) {
            return;
        }

        $this->version = $currentVersion;
        $this->dispatch('teacher-requests-updated');
    }

    public function render(): View
    {
        return view('livewire.teacher.request-change-watcher');
    }

    private function currentClassroomId(): ?int
    {
        return session('current_classroom_id');
    }
}
