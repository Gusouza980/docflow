<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PlatformAdminSeeder extends Seeder
{
    /**
     * Credenciais locais: platform@docflow.test / password
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'platform@docflow.test'],
            [
                'name' => 'Admin Plataforma',
                'password' => Hash::make('password'),
                'is_platform_admin' => true,
            ],
        );
    }
}
