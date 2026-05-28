<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('classrooms')
            ->whereNotNull('public_slug')
            ->update([
                'public_slug' => DB::raw('LOWER(public_slug)'),
            ]);

        DB::table('public_display_slugs')
            ->update([
                'slug' => DB::raw('LOWER(slug)'),
            ]);
    }
};
