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
        Schema::create('exercise_session_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_session_id')->constrained('exercise_sessions')->cascadeOnDelete();
            $table->foreignId('source_question_id')->nullable()->constrained('questions')->nullOnDelete();
            $table->foreignId('source_questionnaire_id')->nullable()->constrained('questionnaires')->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->text('prompt_text')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedInteger('points')->default(1);
            $table->text('answer_key')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exercise_session_questions');
    }
};
