<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Move questions from belonging to a questionnaire to belonging to a topic,
     * preserving any existing questionnaire membership through the new pivot.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('topic_id')->nullable()->after('id')->constrained('topics')->cascadeOnDelete();
        });

        $now = now();

        $questions = DB::table('questions')
            ->whereNotNull('questionnaire_id')
            ->orderBy('id')
            ->get(['id', 'questionnaire_id', 'position']);

        foreach ($questions as $question) {
            $topicId = DB::table('questionnaires')
                ->where('id', $question->questionnaire_id)
                ->value('topic_id');

            DB::table('questions')
                ->where('id', $question->id)
                ->update(['topic_id' => $topicId]);

            if ($topicId !== null) {
                DB::table('questionnaire_question')->updateOrInsert(
                    ['questionnaire_id' => $question->questionnaire_id, 'question_id' => $question->id],
                    ['position' => $question->position, 'created_at' => $now, 'updated_at' => $now]
                );
            }
        }

        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['questionnaire_id']);
            $table->dropColumn('questionnaire_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('questionnaire_id')->nullable()->after('id')->constrained('questionnaires')->cascadeOnDelete();
        });

        $now = now();

        $links = DB::table('questionnaire_question')->orderBy('id')->get();

        foreach ($links as $link) {
            DB::table('questions')
                ->where('id', $link->question_id)
                ->whereNull('questionnaire_id')
                ->update(['questionnaire_id' => $link->questionnaire_id, 'updated_at' => $now]);
        }

        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['topic_id']);
            $table->dropColumn('topic_id');
        });
    }
};
