<?php

namespace App\Services;

use App\Models\Classroom;
use Illuminate\Support\Facades\Cache;

class SupportRequestChangeMarker
{
    public function current(Classroom|int|null $classroom): int
    {
        $classroomId = $this->classroomId($classroom);

        if ($classroomId === null) {
            return 0;
        }

        return (int) Cache::get($this->key($classroomId), 0);
    }

    public function touch(Classroom|int|null $classroom): void
    {
        $classroomId = $this->classroomId($classroom);

        if ($classroomId === null) {
            return;
        }

        $key = $this->key($classroomId);

        Cache::add($key, 0);
        Cache::increment($key);
    }

    private function key(int $classroomId): string
    {
        return "support-requests:classroom:{$classroomId}:version";
    }

    private function classroomId(Classroom|int|null $classroom): ?int
    {
        if ($classroom instanceof Classroom) {
            return $classroom->id;
        }

        return $classroom;
    }
}
