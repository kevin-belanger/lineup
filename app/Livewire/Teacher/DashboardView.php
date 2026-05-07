<?php

namespace App\Livewire\Teacher;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class DashboardView extends Component
{
    public string $activeView = 'requests';

    public function showHistory(): void
    {
        $this->activeView = 'history';
    }

    public function showRequests(): void
    {
        $this->activeView = 'requests';
    }

    public function render(): View
    {
        return view('livewire.teacher.dashboard-view');
    }
}
