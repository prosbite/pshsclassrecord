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
        Schema::table('exercise_session_questions', function (Blueprint $table) {
            $table->unsignedBigInteger('selected_option_id')->nullable()->after('graded_at');
            $table->text('response_text')->nullable()->after('selected_option_id');

            // Explicit short name: the auto-generated constraint name would be
            // close to MySQL's 64-character identifier limit.
            $table->foreign('selected_option_id', 'esq_selected_option_fk')
                ->references('id')
                ->on('exercise_session_question_options')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exercise_session_questions', function (Blueprint $table) {
            $table->dropForeign('esq_selected_option_fk');
            $table->dropColumn(['selected_option_id', 'response_text']);
        });
    }
};
