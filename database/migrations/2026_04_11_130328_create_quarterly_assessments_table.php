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
        Schema::create('quarterly_assessments', function (Blueprint $table) {
            $table->id();
            $table->json('assessment')->nullable();
            $table->foreignId('school_year_id')->constrained('school_years')->cascadeOnDelete();
            $table->foreignId('quarter_id')->constrained('quarters')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('sections')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quarterly_assessments');
    }
};
