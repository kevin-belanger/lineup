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
        if (! Schema::hasColumn('users', 'first_name')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('first_name')->nullable()->after('id');
            });
        }

        if (! Schema::hasColumn('users', 'last_name')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('last_name')->nullable()->after('first_name');
            });
        }

        if (Schema::hasColumn('users', 'name')) {
            DB::table('users')
                ->select(['id', 'name'])
                ->orderBy('id')
                ->chunkById(100, function ($users): void {
                    foreach ($users as $user) {
                        $fullName = trim((string) $user->name);
                        $parts = preg_split('/\s+/', $fullName, 2) ?: [];

                        DB::table('users')
                            ->where('id', $user->id)
                            ->update([
                                'first_name' => $parts[0] ?? '',
                                'last_name' => $parts[1] ?? null,
                            ]);
                    }
                });

            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'name')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('name')->nullable()->after('id');
            });

            DB::table('users')
                ->select(['id', 'first_name', 'last_name'])
                ->orderBy('id')
                ->chunkById(100, function ($users): void {
                    foreach ($users as $user) {
                        DB::table('users')
                            ->where('id', $user->id)
                            ->update([
                                'name' => trim(implode(' ', array_filter([
                                    $user->first_name,
                                    $user->last_name,
                                ], fn ($value): bool => $value !== null && $value !== ''))),
                            ]);
                    }
                });
        }

        if (Schema::hasColumn('users', 'first_name')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('first_name');
            });
        }

        if (Schema::hasColumn('users', 'last_name')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('last_name');
            });
        }
    }
};
