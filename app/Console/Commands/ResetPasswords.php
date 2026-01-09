<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetPasswords extends Command
{
    protected $signature = 'users:reset-passwords';
    protected $description = 'Reset demo user passwords';

    public function handle()
    {
        User::where('email', 'admin@pmu-academy.com')
            ->update(['password' => Hash::make('admin123')]);
        
        User::where('email', 'edukator@pmu-academy.com')
            ->update(['password' => Hash::make('edukator123')]);
        
        User::where('email', 'student@pmu-academy.com')
            ->update(['password' => Hash::make('student123')]);

        $this->info('Passwords reset successfully!');
    }
}
