<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // updateOrCreate kullanıyoruz ki komutu 10 kere de çalıştırsan çakışma hatası vermesin, hesabı güncelleyip geçsin.
        User::updateOrCreate(
            ['email' => 'admin@vomsis.com'], // Sistem bu e-postayı arar
            [
                'name' => 'Admin Necmi',
                'password' => Hash::make('12345678'), // Şifreni buradan ayarlayabilirsin
                'role' => 'admin',
                'can_view_pos' => 1,
                'can_view_physical_pos' => 1,
                'can_create_tags' => 1,
                'email_verified_at' => now(),
            ]
        );
    }
}