<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_requests', function (Blueprint $table): void {
            $table->unsignedInteger('calculated_wait_time_minutes')->nullable()->after('completed_at');
            $table->unsignedInteger('calculated_response_time_minutes')->nullable()->after('calculated_wait_time_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('support_requests', function (Blueprint $table): void {
            $table->dropColumn([
                'calculated_wait_time_minutes',
                'calculated_response_time_minutes',
            ]);
        });
    }
};
