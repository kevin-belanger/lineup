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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_student')->default(true)->after('password')->index();
            $table->boolean('is_teacher')->default(false)->after('is_student')->index();
            $table->boolean('is_admin')->default(false)->after('is_teacher')->index();
            $table->boolean('is_approved')->default(false)->after('is_admin')->index();
            $table->timestamp('approved_at')->nullable()->after('is_approved');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('approved_by')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'is_student',
                'is_teacher',
                'is_admin',
                'is_approved',
                'approved_at',
                'is_active',
            ]);
        });
    }
};
