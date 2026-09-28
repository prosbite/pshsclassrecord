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
        Schema::create('exercise_session_question_options', function (Blueprint $table) {
            $table->id();
            // Explicit short constraint names: the auto-generated names would
            // exceed MySQL's 64-character identifier limit.
            $table->unsignedBigInteger('exercise_session_question_id');
            $table->unsignedBigInteger('source_option_id')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->string('label');
            $table->boolean('is_correct')->default(false);
            $table->timestamps();

            $table->foreign('exercise_session_question_id', 'esqo_session_fk')
                ->references('id')
                ->on('exercise_session_questions')
                ->cascadeOnDelete();

            $table->foreign('source_option_id', 'esqo_source_option_fk')
                ->references('id')
                ->on('question_options')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exercise_session_question_options');
    }
};
