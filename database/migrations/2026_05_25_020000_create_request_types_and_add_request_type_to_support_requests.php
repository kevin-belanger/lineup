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
        Schema::create('request_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['sort_order', 'name'], 'request_types_sort_name_index');
        });

        Schema::table('support_requests', function (Blueprint $table) {
            $table->string('request_type')->nullable()->after('type');
            $table->index('request_type');
        });

        DB::table('support_requests')
            ->where('type', 'explanation')
            ->update(['request_type' => 'Explanation']);

        DB::table('support_requests')
            ->where('type', 'validation')
            ->update(['request_type' => 'Validation']);

        DB::table('support_requests')
            ->where('type', 'correction')
            ->update(['request_type' => 'Correction']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('support_requests', function (Blueprint $table) {
            $table->dropIndex(['request_type']);
            $table->dropColumn('request_type');
        });

        Schema::dropIfExists('request_types');
    }
};
