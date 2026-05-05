<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('support_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('moodle_tile_number');
            $table->string('table_number');
            $table->string('type');
            $table->string('status')->default('waiting');
            $table->text('comment')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['classroom_id', 'status']);
            $table->index(['assigned_teacher_id', 'status']);
            $table->index(['student_id', 'status']);
            $table->index('type');
            $table->index('moodle_tile_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_requests');
    }
};
