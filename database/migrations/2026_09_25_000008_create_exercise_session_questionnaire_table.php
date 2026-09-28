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
        Schema::create('exercise_session_questionnaire', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_session_id')->constrained('exercise_sessions')->cascadeOnDelete();
            $table->foreignId('questionnaire_id')->nullable()->constrained('questionnaires')->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['exercise_session_id', 'questionnaire_id'], 'exercise_session_questionnaire_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exercise_session_questionnaire');
    }
};
