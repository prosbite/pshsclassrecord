<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->after('email')->unique();
        });

        $assigned = [];

        DB::table('users')
            ->orderBy('id')
            ->get()
            ->each(function ($user) use (&$assigned) {
                $base = Str::before($user->email ?? '', '@');
                $slug = Str::slug($base ?: "user-{$user->id}", '-');

                if ($slug === '') {
                    $slug = "user-{$user->id}";
                }

                $username = $slug;
                $suffix = 1;

                while (in_array($username, $assigned, true)) {
                    $username = "{$slug}-{$suffix}";
                    $suffix++;
                }

                $assigned[] = $username;

                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['username' => $username]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
