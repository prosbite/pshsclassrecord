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
        Schema::table('questions', function (Blueprint $table) {
            $table->string('type')->default('text')->after('id');
        });

        Schema::table('exercise_session_questions', function (Blueprint $table) {
            $table->string('type')->default('text')->after('exercise_session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('exercise_session_questions', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
