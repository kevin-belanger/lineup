<?php

namespace App\Livewire\Teacher;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class DashboardView extends Component
{
    public function render(): View
    {
        return view('livewire.teacher.dashboard-view');
    }
}
