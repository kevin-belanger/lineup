<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('local_subject', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('local_id')->constrained('classrooms')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['local_id', 'subject_id']);
        });

        $now = now();
        $associations = DB::table('subjects')
            ->whereNotNull('classroom_id')
            ->select('classroom_id', 'id')
            ->get()
            ->map(fn (object $subject): array => [
                'local_id' => $subject->classroom_id,
                'subject_id' => $subject->id,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($associations !== []) {
            DB::table('local_subject')->insertOrIgnore($associations);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('local_subject');
    }
};
