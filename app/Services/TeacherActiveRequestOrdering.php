<?php

namespace App\Services;

use App\Models\SupportRequest;
use App\Models\TeacherActiveRequestOrder;

class TeacherActiveRequestOrdering
{
    public function moveToTop(int $teacherId, int $supportRequestId): void
    {
        TeacherActiveRequestOrder::query()->updateOrCreate(
            [
                'teacher_id' => $teacherId,
                'support_request_id' => $supportRequestId,
            ],
            [
                'sort_order' => $this->nextSortOrder($teacherId),
            ],
        );
    }

    public function moveToBottom(int $teacherId, int $supportRequestId): void
    {
        TeacherActiveRequestOrder::query()->updateOrCreate(
            [
                'teacher_id' => $teacherId,
                'support_request_id' => $supportRequestId,
            ],
            [
                'sort_order' => $this->previousSortOrder($teacherId),
            ],
        );
    }

    /**
     * @param  array<int, int|string>  $orderedSupportRequestIds
     */
    public function reorder(int $teacherId, array $orderedSupportRequestIds): void
    {
        $orderedSupportRequestIds = collect($orderedSupportRequestIds)
            ->map(fn (int|string $id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($orderedSupportRequestIds->isEmpty()) {
            return;
        }

        $activeIds = SupportRequest::query()
            ->whereIn('id', $orderedSupportRequestIds)
            ->where('assigned_teacher_id', $teacherId)
            ->whereIn('status', SupportRequest::teacherActiveStatuses())
            ->pluck('id')
            ->all();

        $activeIdLookup = array_flip($activeIds);
        $orderedActiveIds = $orderedSupportRequestIds
            ->filter(fn (int $id): bool => isset($activeIdLookup[$id]))
            ->values();

        $sortOrder = $orderedActiveIds->count();

        foreach ($orderedActiveIds as $supportRequestId) {
            TeacherActiveRequestOrder::query()->updateOrCreate(
                [
                    'teacher_id' => $teacherId,
                    'support_request_id' => $supportRequestId,
                ],
                [
                    'sort_order' => $sortOrder,
                ],
            );

            $sortOrder--;
        }
    }

    public function remove(int $teacherId, int $supportRequestId): void
    {
        TeacherActiveRequestOrder::query()
            ->where('teacher_id', $teacherId)
            ->where('support_request_id', $supportRequestId)
            ->delete();
    }

    public function removeForRequest(int $supportRequestId): void
    {
        TeacherActiveRequestOrder::query()
            ->where('support_request_id', $supportRequestId)
            ->delete();
    }

    /**
     * @param  iterable<int, int>  $supportRequestIds
     */
    public function removeForRequests(iterable $supportRequestIds): void
    {
        $supportRequestIds = collect($supportRequestIds)->filter()->values();

        if ($supportRequestIds->isEmpty()) {
            return;
        }

        TeacherActiveRequestOrder::query()
            ->whereIn('support_request_id', $supportRequestIds)
            ->delete();
    }

    public function removeInactive(): void
    {
        TeacherActiveRequestOrder::query()
            ->whereDoesntHave('supportRequest', function ($query): void {
                $query
                    ->whereColumn('support_requests.assigned_teacher_id', 'teacher_active_request_orders.teacher_id')
                    ->whereIn('support_requests.status', SupportRequest::teacherActiveStatuses());
            })
            ->delete();
    }

    private function nextSortOrder(int $teacherId): int
    {
        return ((int) TeacherActiveRequestOrder::query()
            ->where('teacher_id', $teacherId)
            ->max('sort_order')) + 1;
    }

    private function previousSortOrder(int $teacherId): int
    {
        $minimumSortOrder = TeacherActiveRequestOrder::query()
            ->where('teacher_id', $teacherId)
            ->min('sort_order');

        if ($minimumSortOrder === null) {
            return -1;
        }

        return ((int) $minimumSortOrder) - 1;
    }
}
