<?php

use App\Models\SubjectRequestField;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_request_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('key');
            $table->string('type')->default(SubjectRequestField::TYPE_TEXT);
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['subject_id', 'key']);
            $table->index(['subject_id', 'archived_at', 'sort_order']);
        });

        Schema::create('support_request_field_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('support_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_request_field_id')->nullable()->constrained()->nullOnDelete();
            $table->string('field_name');
            $table->string('field_key');
            $table->string('field_type');
            $table->text('value')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['support_request_id', 'sort_order'], 'request_answers_request_sort_index');
            $table->index('subject_request_field_id', 'request_answers_field_index');
        });

        $this->migrateMoodleTiles();
        $this->migrateSubjectUrls();
    }

    public function down(): void
    {
        DB::table('subjects')
            ->whereNotNull('url')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $subject): void {
                DB::table('subjects')
                    ->where('id', $subject->id)
                    ->update([
                        'url' => preg_replace('/\[tuile\s+moodle\]/iu', '[section]', (string) $subject->url),
                        'updated_at' => now(),
                    ]);
            });

        Schema::dropIfExists('support_request_field_answers');
        Schema::dropIfExists('subject_request_fields');
    }

    private function migrateMoodleTiles(): void
    {
        $now = now();
        $fieldKey = SubjectRequestField::keyForName('Tuile Moodle');
        $subjectFieldIds = [];

        DB::table('subjects')
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $subject) use ($now, $fieldKey, &$subjectFieldIds): void {
                $fieldId = DB::table('subject_request_fields')->insertGetId([
                    'subject_id' => $subject->id,
                    'name' => 'Tuile Moodle',
                    'key' => $fieldKey,
                    'type' => SubjectRequestField::TYPE_INTEGER,
                    'is_required' => true,
                    'sort_order' => 0,
                    'archived_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $subjectFieldIds[$subject->id] = $fieldId;
            });

        DB::table('support_requests')
            ->whereNotNull('subject_id')
            ->whereNotNull('moodle_tile_number')
            ->orderBy('id')
            ->get(['id', 'subject_id', 'moodle_tile_number'])
            ->each(function (object $supportRequest) use ($now, $fieldKey, $subjectFieldIds): void {
                $fieldId = $subjectFieldIds[$supportRequest->subject_id] ?? null;

                if ($fieldId === null) {
                    return;
                }

                DB::table('support_request_field_answers')->insert([
                    'support_request_id' => $supportRequest->id,
                    'subject_request_field_id' => $fieldId,
                    'field_name' => 'Tuile Moodle',
                    'field_key' => $fieldKey,
                    'field_type' => SubjectRequestField::TYPE_INTEGER,
                    'value' => (string) $supportRequest->moodle_tile_number,
                    'sort_order' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    private function migrateSubjectUrls(): void
    {
        DB::table('subjects')
            ->whereNotNull('url')
            ->orderBy('id')
            ->get(['id', 'url'])
            ->each(function (object $subject): void {
                DB::table('subjects')
                    ->where('id', $subject->id)
                    ->update([
                        'url' => preg_replace('/\[section\]/iu', '[tuile moodle]', (string) $subject->url),
                        'updated_at' => now(),
                    ]);
            });
    }
};
