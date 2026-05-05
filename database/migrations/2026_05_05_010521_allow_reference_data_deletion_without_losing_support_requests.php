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
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropForeign(['classroom_id']);
        });

        Schema::table('support_requests', function (Blueprint $table) {
            $table->dropForeign(['classroom_id']);
            $table->dropForeign(['subject_id']);
        });

        Schema::table('support_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('classroom_id')->nullable()->change();
            $table->unsignedBigInteger('subject_id')->nullable()->change();
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->foreign('classroom_id')->references('id')->on('classrooms')->nullOnDelete();
        });

        Schema::table('support_requests', function (Blueprint $table) {
            $table->foreign('classroom_id')->references('id')->on('classrooms')->nullOnDelete();
            $table->foreign('subject_id')->references('id')->on('subjects')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropForeign(['classroom_id']);
        });

        Schema::table('support_requests', function (Blueprint $table) {
            $table->dropForeign(['classroom_id']);
            $table->dropForeign(['subject_id']);
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->foreign('classroom_id')->references('id')->on('classrooms')->restrictOnDelete();
        });

        Schema::table('support_requests', function (Blueprint $table) {
            $table->foreign('classroom_id')->references('id')->on('classrooms')->restrictOnDelete();
            $table->foreign('subject_id')->references('id')->on('subjects')->restrictOnDelete();
        });
    }
};
