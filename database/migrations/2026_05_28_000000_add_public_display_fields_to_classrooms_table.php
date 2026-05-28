<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table): void {
            $table->boolean('public_enabled')->default(false)->after('is_active');
            $table->string('public_slug', 5)->nullable()->unique()->after('public_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table): void {
            $table->dropUnique(['public_slug']);
            $table->dropColumn(['public_enabled', 'public_slug']);
        });
    }
};
