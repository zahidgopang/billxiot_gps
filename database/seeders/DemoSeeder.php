<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Device;
use App\Models\DeviceLocation;

class DemoSeeder extends Seeder
{
    public function run()
    {
        $admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@demo.test',
            'password' => bcrypt('password'),
            'role' => 'admin'
        ]);

        $user = User::factory()->create([
            'name' => 'Demo User',
            'email' => 'user@demo.test',
            'password' => bcrypt('password'),
            'role' => 'user'
        ]);

        $device = Device::create([
            'imei' => '867530912345678',
            'name' => 'Demo Bike',
            'user_id' => $user->id,
            'status' => 'active'
        ]);

        $centerLat = 24.8607;
        $centerLng = 67.0011;

        for ($i = 0; $i < 50; $i++) {
            DeviceLocation::create([
                'device_id' => $device->id,
                'lat' => $centerLat + mt_rand(-200,200)/100000.0,
                'lng' => $centerLng + mt_rand(-200,200)/100000.0,
                'speed' => mt_rand(0,80),
                'heading' => mt_rand(0,360),
                'battery_level' => mt_rand(40,100),
                'recorded_at' => now()->subSeconds(10 * $i)
            ]);
        }
    }
}
