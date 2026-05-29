<?php

namespace Database\Seeders;

use App\Models\SuperAdminUser;
use Illuminate\Database\Seeder;

class SuperAdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPER_ADMIN_EMAIL', 'admin@pms.local');
        $name = env('SUPER_ADMIN_NAME', 'PMS Admin');
        $password = env('SUPER_ADMIN_PASSWORD', 'password');

        SuperAdminUser::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password, // hashed cast in model
            ],
        );

        $this->command->info("Super admin: {$email} / [password from .env or 'password']");
    }
}
