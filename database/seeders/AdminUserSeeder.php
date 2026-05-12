<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the initial administrator account.
     */
    public function run(): void
    {
        $configuredName = trim((string) env('ADMIN_NAME', ''));
        $configuredEmail = trim((string) env('ADMIN_EMAIL', ''));
        $configuredPassword = (string) env('ADMIN_PASSWORD', '');

        $name = $configuredName !== '' ? $configuredName : 'Administrator';
        $email = $configuredEmail !== '' ? $configuredEmail : 'admin@example.com';
        $password = $configuredPassword !== '' ? $configuredPassword : 'password';

        $admin = User::query()->where('email', $email)->first();

        if ($admin === null && $configuredEmail === '') {
            $admin = User::query()->where('is_admin', true)->first();
        }

        $admin ??= new User(['email' => $email]);

        $admin->fill([
            'name' => $name !== '' ? $name : 'Administrator',
            'is_student' => false,
            'is_teacher' => false,
            'is_admin' => true,
            'is_approved' => true,
            'approved_at' => $admin->approved_at ?? now(),
            'is_active' => true,
        ]);

        if (! $admin->exists) {
            $admin->password = Hash::make($password);
        }

        $admin->save();
    }
}
