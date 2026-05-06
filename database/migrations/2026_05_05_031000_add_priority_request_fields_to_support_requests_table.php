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
        Schema::table('support_requests', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
        });

        Schema::table('support_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('student_id')->nullable()->change();
            $table->unsignedInteger('moodle_tile_number')->nullable()->change();
            $table->string('table_number')->nullable()->change();
            $table->string('type')->nullable()->change();
            $table->boolean('is_priority')->default(false)->after('assigned_teacher_id');
            $table->foreignId('priority_requested_by_teacher_id')->nullable()->after('is_priority')->constrained('users')->nullOnDelete();
        });

        Schema::table('support_requests', function (Blueprint $table) {
            $table->foreign('student_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['is_priority', 'classroom_id', 'status']);
            $table->index(['priority_requested_by_teacher_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('support_requests', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->dropForeign(['priority_requested_by_teacher_id']);
            $table->dropIndex(['is_priority', 'classroom_id', 'status']);
            $table->dropIndex(['priority_requested_by_teacher_id', 'status']);
        });

        Schema::table('support_requests', function (Blueprint $table) {
            $table->dropColumn(['is_priority', 'priority_requested_by_teacher_id']);
            $table->unsignedBigInteger('student_id')->nullable(false)->change();
            $table->unsignedInteger('moodle_tile_number')->nullable(false)->change();
            $table->string('table_number')->nullable(false)->change();
            $table->string('type')->nullable(false)->change();
        });

        Schema::table('support_requests', function (Blueprint $table) {
            $table->foreign('student_id')->references('id')->on('users')->restrictOnDelete();
        });
    }
};
