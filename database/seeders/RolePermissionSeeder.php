<?php
// ===========================
// database/seeders/RolePermissionSeeder.php
// ===========================

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Roles (only if not exists)
        $superAdminRole = Role::firstOrCreate(['name' => 'superadmin']);
        $mcrRole = Role::firstOrCreate(['name' => 'mcr']);
        $managerRole = Role::firstOrCreate(['name' => 'manager']);

        // Define permissions that we want to ensure exist
        $equipmentPermissions = [
            'view_equipment',
            'view_any_equipment',
            'create_equipment',
            'update_equipment',
            'delete_equipment',
            'delete_any_equipment',
        ];

        $sessionPermissions = [
            'view_loading::session',
            'view_any_loading::session',
            'create_loading::session',
            'update_loading::session',
            'delete_loading::session',
            'delete_any_loading::session',
        ];

        $breakdownPermissions = [
            'view_equipment::breakdown',
            'view_any_equipment::breakdown',
            'create_equipment::breakdown',
            'update_equipment::breakdown',
            'delete_equipment::breakdown',
            'delete_any_equipment::breakdown',
        ];

        $areaPermissions = [
            'view_stacking::area',
            'view_any_stacking::area',
            'create_stacking::area',
            'update_stacking::area',
            'delete_stacking::area',
            'delete_any_stacking::area',
        ];

        // Combine our specific permissions
        $operationPermissions = array_merge(
            $equipmentPermissions,
            $sessionPermissions,
            $breakdownPermissions,
            $areaPermissions
        );

        // Create permissions only if they don't exist
        foreach ($operationPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Assign permissions to roles
        
        // SuperAdmin: Give all permissions (including Shield auto-generated ones)
        $superAdminRole->syncPermissions(Permission::all());

        // MCR Role: Operations access only
        $mcrRole->syncPermissions($operationPermissions);

        // Manager Role: Read-only access to operations data
        $managerPermissions = [
            'view_equipment',
            'view_any_equipment',
            'view_loading::session',
            'view_any_loading::session',
            'view_equipment::breakdown',
            'view_any_equipment::breakdown',
            'view_stacking::area',
            'view_any_stacking::area',
        ];
        $managerRole->syncPermissions($managerPermissions);

        // Create default users and assign roles
        
        // Super Admin User
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@goldmine.com'],
            [
                'name' => 'Super Administrator',
                'password' => Hash::make('password'),
                'role' => 'superadmin',
                'is_active' => true,
            ]
        );
        $superAdmin->syncRoles([$superAdminRole]);

        // MCR Users
        $mcrUserA = User::firstOrCreate(
            ['email' => 'mcr.shifta@goldmine.com'],
            [
                'name' => 'MCR Shift A',
                'password' => Hash::make('password'),
                'role' => 'mcr',
                'shift' => 'A',
                'is_active' => true,
            ]
        );
        $mcrUserA->syncRoles([$mcrRole]);

        $mcrUserB = User::firstOrCreate(
            ['email' => 'mcr.shiftb@goldmine.com'],
            [
                'name' => 'MCR Shift B',
                'password' => Hash::make('password'),
                'role' => 'mcr',
                'shift' => 'B',
                'is_active' => true,
            ]
        );
        $mcrUserB->syncRoles([$mcrRole]);

        $mcrUserC = User::firstOrCreate(
            ['email' => 'mcr.shiftc@goldmine.com'],
            [
                'name' => 'MCR Shift C',
                'password' => Hash::make('password'),
                'role' => 'mcr',
                'shift' => 'C',
                'is_active' => true,
            ]
        );
        $mcrUserC->syncRoles([$mcrRole]);

        // Manager User
        $manager = User::firstOrCreate(
            ['email' => 'manager@goldmine.com'],
            [
                'name' => 'Production Manager',
                'password' => Hash::make('password'),
                'role' => 'manager',
                'is_active' => true,
            ]
        );
        $manager->syncRoles([$managerRole]);

        $this->command->info('Roles and permissions setup successfully!');
        $this->command->info('Users with roles:');
        $this->command->info('- Super Admin: admin@goldmine.com / password');
        $this->command->info('- MCR Shift A: mcr.shifta@goldmine.com / password');
        $this->command->info('- MCR Shift B: mcr.shiftb@goldmine.com / password');
        $this->command->info('- MCR Shift C: mcr.shiftc@goldmine.com / password');
        $this->command->info('- Manager: manager@goldmine.com / password');
    }
}