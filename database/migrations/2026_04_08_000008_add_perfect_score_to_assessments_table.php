<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint ) {
            ->integer('perfect_score')->default(100)->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint ) {
            ->dropColumn('perfect_score');
        });
    }
};
