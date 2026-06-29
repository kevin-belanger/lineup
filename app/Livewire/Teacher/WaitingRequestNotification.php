<?php

namespace App\Livewire\Teacher;

use App\Models\SupportRequest;
use App\Services\TeacherPageTitle;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class WaitingRequestNotification extends Component
{
    public int $count = 0;

    public string $signature = '';

    public function mount(): void
    {
        $snapshot = $this->waitingRequestSnapshot();

        $this->count = $snapshot['count'];
        $this->signature = $snapshot['signature'];
    }

    public function check(TeacherPageTitle $title): void
    {
        $snapshot = $this->waitingRequestSnapshot();

        if ($snapshot['signature'] === $this->signature) {
            return;
        }

        $this->count = $snapshot['count'];
        $this->signature = $snapshot['signature'];

        $this->dispatch('teacher-waiting-requests-count-updated', count: $this->count);
        $this->updatePageTitle($title);
    }

    public function updatePageTitle(TeacherPageTitle $title): void
    {
        $this->dispatch(
            'teacher-page-title-updated',
            title: $title->forClassroom($this->currentClassroomId(), auth()->user()),
        );
    }

    public function render(): View
    {
        return view('livewire.teacher.waiting-request-notification');
    }

    /**
     * @return array{count: int, signature: string}
     */
    private function waitingRequestSnapshot(): array
    {
        $classroomId = $this->currentClassroomId();

        if ($classroomId === null) {
            return ['count' => 0, 'signature' => ''];
        }

        $requestIds = SupportRequest::query()
            ->where('classroom_id', $classroomId)
            ->where('status', SupportRequest::STATUS_WAITING)
            ->oldest('created_at')
            ->oldest('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return [
            'count' => count($requestIds),
            'signature' => implode('|', $requestIds),
        ];
    }

    private function currentClassroomId(): ?int
    {
        return session('current_classroom_id');
    }
}
