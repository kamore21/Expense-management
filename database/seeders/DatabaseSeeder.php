<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $demoUser = User::query()->firstOrCreate(
            ['email' => 'admin@ledgerflow.test'],
            ['name' => 'Admin User', 'password' => 'admin123'],
        );
        if ($demoUser->email_verified_at === null) {
            $demoUser->forceFill(['email_verified_at' => now()])->save();
        }

        $this->call(DemoFinanceSeeder::class);
    }
}
