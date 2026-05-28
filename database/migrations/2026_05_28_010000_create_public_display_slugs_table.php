<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_display_slugs', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 5)->unique();
            $table->timestamps();
        });

        DB::table('classrooms')
            ->whereNotNull('public_slug')
            ->pluck('public_slug')
            ->unique()
            ->each(function (string $slug): void {
                DB::table('public_display_slugs')->insert([
                    'slug' => $slug,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_display_slugs');
    }
};
