<?php

// ===========================
// 8. database/seeders/DatabaseSeeder.php - Sample Data
// ===========================

namespace Database\Seeders;

use App\Models\User;
use App\Models\Equipment;
use App\Models\StackingArea;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
               // Run role and permission seeder first
        $this->call([
            RolePermissionSeeder::class,
        ]);
        
        // Create users
        User::create([
            'name' => 'Super Admin',
            'email' => 'admin@goldmine.com',
            'password' => Hash::make('password'),
            'role' => 'superadmin',
        ]);

        User::create([
            'name' => 'MCR Shift A',
            'email' => 'mcr.shifta@goldmine.com',
            'password' => Hash::make('password'),
            'role' => 'mcr',
            'shift' => 'A',
        ]);

        User::create([
            'name' => 'Production Manager',
            'email' => 'manager@goldmine.com',
            'password' => Hash::make('password'),
            'role' => 'manager',
        ]);

        // Create Equipment
        Equipment::create([
            'code' => 'DT-001',
            'type' => 'dumptruck',
            'brand' => 'Caterpillar',
            'model' => '777D',
            'capacity' => 50.00,
            'year_manufacture' => 2020,
        ]);

        Equipment::create([
            'code' => 'DT-002',
            'type' => 'dumptruck',
            'brand' => 'Caterpillar',
            'model' => '777D',
            'capacity' => 50.00,
            'year_manufacture' => 2020,
        ]);

        Equipment::create([
            'code' => 'EX-001',
            'type' => 'excavator',
            'brand' => 'Caterpillar',
            'model' => '390F',
            'capacity' => 3.50,
            'year_manufacture' => 2019,
        ]);

        Equipment::create([
            'code' => 'EX-002',
            'type' => 'excavator',
            'brand' => 'Komatsu',
            'model' => 'PC800',
            'capacity' => 3.80,
            'year_manufacture' => 2021,
        ]);

        // Create Stacking Areas
        StackingArea::create([
            'code' => 'AREA-A1',
            'name' => 'Area Stacking A1',
            'location' => 'Sektor Utara - Koordinat: -7.123, 112.456',
            'latitude' => -7.123,
            'longitude' => 112.456,
        ]);

        StackingArea::create([
            'code' => 'AREA-B2',
            'name' => 'Area Stacking B2',
            'location' => 'Sektor Tengah - Koordinat: -7.124, 112.457',
            'latitude' => -7.124,
            'longitude' => 112.457,
        ]);

        StackingArea::create([
            'code' => 'AREA-C1',
            'name' => 'Area Stacking C1',
            'location' => 'Sektor Selatan - Koordinat: -7.125, 112.458',
            'latitude' => -7.125,
            'longitude' => 112.458,
        ]);
    }
}