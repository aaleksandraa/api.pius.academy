<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'educator']);
        Role::firstOrCreate(['name' => 'student']);

        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@pmu-academy.com'],
            ['name' => 'Administrator', 'password' => Hash::make('admin123')]
        );
        if (!$admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }

        // Create educator
        $educator = User::firstOrCreate(
            ['email' => 'edukator@pmu-academy.com'],
            ['name' => 'Edukator Demo', 'password' => Hash::make('edukator123')]
        );
        if (!$educator->hasRole('educator')) {
            $educator->assignRole('educator');
        }

        // Create student
        $student = User::firstOrCreate(
            ['email' => 'student@pmu-academy.com'],
            ['name' => 'Student Demo', 'password' => Hash::make('student123'), 'educator_id' => $educator->id]
        );
        if (!$student->hasRole('student')) {
            $student->assignRole('student');
        }
    }
}
