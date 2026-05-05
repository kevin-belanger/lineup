<?php

namespace App\Livewire\Student;

use App\Models\SupportRequest;
use App\Services\SupportRequestChangeMarker;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ActiveRequests extends Component
{
    public ?string $notice = null;

    public function cancel(int $supportRequestId): void
    {
        $this->notice = null;
        $supportRequest = SupportRequest::query()
            ->whereKey($supportRequestId)
            ->where('student_id', auth()->id())
            ->first(['id', 'classroom_id']);

        $updated = SupportRequest::query()
            ->whereKey($supportRequestId)
            ->where('student_id', auth()->id())
            ->where('status', SupportRequest::STATUS_WAITING)
            ->update([
                'status' => SupportRequest::STATUS_CANCELLED,
                'updated_at' => now(),
            ]);

        $this->notice = $updated === 1
            ? 'Demande annulee.'
            : 'La demande a ete mise a jour.';

        if ($updated === 1) {
            app(SupportRequestChangeMarker::class)->touch($supportRequest?->classroom_id);
        }
    }

    public function render(): View
    {
        return view('livewire.student.active-requests', [
            'requests' => SupportRequest::query()
                ->with(['classroom:id,name', 'subject:id,name', 'assignedTeacher:id,name'])
                ->where('student_id', auth()->id())
                ->whereIn('status', SupportRequest::activeStatuses())
                ->latest()
                ->get(),
            'statusLabels' => SupportRequest::statusLabels(),
            'typeLabels' => SupportRequest::typeLabels(),
        ]);
    }
}
