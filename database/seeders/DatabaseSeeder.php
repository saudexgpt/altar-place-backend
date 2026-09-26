<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(SubscriptionPlanSeeder::class);

        $superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'email_verified_at' => now(),
        ]);
        $superAdmin->assignRole('super-admin');

        $listener = User::factory()->create([
            'name' => 'Test Listener',
            'email' => 'test@example.com',
            'email_verified_at' => now(),
        ]);
        $listener->assignRole('listener');

        $this->call(CatalogSeeder::class);
        $this->call(AdvertisingSeeder::class);
    }
}
