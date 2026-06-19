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
        Schema::create('classroom_opening_hours', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->json('days');
            $table->time('opens_at');
            $table->time('closes_at');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['classroom_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classroom_opening_hours');
    }
};
