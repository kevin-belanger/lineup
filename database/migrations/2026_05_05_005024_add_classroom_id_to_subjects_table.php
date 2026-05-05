<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->foreignId('classroom_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
        });

        $firstClassroomId = DB::table('classrooms')->orderBy('id')->value('id');

        if ($firstClassroomId !== null) {
            DB::table('subjects')
                ->whereNull('classroom_id')
                ->update(['classroom_id' => $firstClassroomId]);
        }

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->unique(['classroom_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropUnique(['classroom_id', 'name']);
            $table->dropConstrainedForeignId('classroom_id');
            $table->unique('name');
        });
    }
};
