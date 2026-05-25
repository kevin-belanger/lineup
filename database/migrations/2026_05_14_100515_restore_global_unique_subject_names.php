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
        $duplicateNames = DB::table('subjects')
            ->select('name', DB::raw('COUNT(*) as duplicate_count'))
            ->groupBy('name')
            ->having('duplicate_count', '>', 1)
            ->pluck('name')
            ->all();

        if ($duplicateNames !== []) {
            throw new RuntimeException(
                'Cannot add global unique subject-name constraint because duplicate subject names exist: '
                .implode(', ', $duplicateNames)
            );
        }

        if (! $this->indexExists('subjects', 'subjects_classroom_id_index')) {
            Schema::table('subjects', function (Blueprint $table): void {
                $table->index('classroom_id');
            });
        }

        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropUnique(['classroom_id', 'name']);
            $table->unique('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropUnique(['name']);
            $table->unique(['classroom_id', 'name']);
        });

        if ($this->indexExists('subjects', 'subjects_classroom_id_index')) {
            Schema::table('subjects', function (Blueprint $table): void {
                $table->dropIndex(['classroom_id']);
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('{$table}')"))
                ->contains(fn (object $row): bool => $row->name === $index);
        }

        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
