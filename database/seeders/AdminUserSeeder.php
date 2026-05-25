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
        $configuredFirstName = trim((string) env('ADMIN_FIRST_NAME', ''));
        $configuredLastName = trim((string) env('ADMIN_LAST_NAME', ''));
        $configuredEmail = trim((string) env('ADMIN_EMAIL', ''));
        $configuredPassword = (string) env('ADMIN_PASSWORD', '');

        $firstName = $configuredFirstName !== '' ? $configuredFirstName : 'Administrator';
        $lastName = $configuredLastName !== '' ? $configuredLastName : 'Admin';
        $email = $configuredEmail !== '' ? $configuredEmail : 'admin@example.com';
        $password = $configuredPassword !== '' ? $configuredPassword : 'password';

        $admin = User::query()->where('email', $email)->first();

        if ($admin === null && $configuredEmail === '') {
            $admin = User::query()->where('is_admin', true)->first();
        }

        $admin ??= new User(['email' => $email]);

        $admin->fill([
            'first_name' => $firstName !== '' ? $firstName : 'Administrator',
            'last_name' => $lastName,
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
