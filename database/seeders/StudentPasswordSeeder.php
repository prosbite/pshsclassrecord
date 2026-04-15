<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StudentPasswordSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()
            ->where('role', 'student')
            ->whereNotNull('email')
            ->each(function (User $user): void {
                $user->password = Hash::make(Str::lower(trim((string) $user->email)));
                $user->save();
            });
    }
}
