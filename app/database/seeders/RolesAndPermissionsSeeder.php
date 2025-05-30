<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // === PERMISSIONS ===
        // ... (permission lain yang sudah ada)

        // Permissions untuk mengelola Roles & Permissions (AKSES KHUSUS)
        Permission::findOrCreate('view_any_roles', 'web');
        Permission::findOrCreate('view_roles', 'web');
        Permission::findOrCreate('create_roles', 'web');
        Permission::findOrCreate('update_roles', 'web');
        Permission::findOrCreate('delete_roles', 'web');
        Permission::findOrCreate('assign_permissions_to_role', 'web');

        Permission::findOrCreate('view_any_permissions', 'web');
        Permission::findOrCreate('view_permissions', 'web');
        // Biasanya 'create', 'update', 'delete' untuk permissions tidak di-enable dari UI demi keamanan
        // Permission::findOrCreate('create_permissions', 'web');
        // Permission::findOrCreate('update_permissions', 'web');
        // Permission::findOrCreate('delete_permissions', 'web');


        // === ROLES ===
        $roleAdmin = Role::findOrCreate('Admin', 'web');
        $roleTeller = Role::findOrCreate('Teller', 'web');
        $roleKeuangan = Role::findOrCreate('Manajer Keuangan', 'web');
        $roleAnggota = Role::findOrCreate('Anggota', 'web');


        // === MENETAPKAN PERMISSIONS KE ROLES ===

        // Admin
        $adminPermissions = Permission::all()->pluck('name')->toArray(); // Admin dapat semua
        $roleAdmin->syncPermissions($adminPermissions);


        // Manajer Keuangan
        $keuanganPermissions = [
            'view_filament_admin_panel',
            'approve_loans', 'view_any_loans', 'view_loans', 'update_loans',
            'view_any_savings', 'view_savings',
            'view_any_payments', 'view_payments',
            'view_any_users', 'view_users',
            // TAMBAHKAN INI UNTUK MANAJER KEUANGAN JIKA MEREKA BOLEH LIHAT/KELOLA ROLES & PERMISSIONS
            'view_any_roles',       // Boleh lihat daftar peran
            'view_roles',           // Boleh lihat detail peran
            // 'create_roles',      // Mungkin tidak untuk MK?
            // 'update_roles',      // Mungkin tidak untuk MK?
            // 'delete_roles',      // Mungkin tidak untuk MK?
            // 'assign_permissions_to_role', // Mungkin tidak untuk MK?
            'view_any_permissions', // Boleh lihat daftar izin
            'view_permissions',     // Boleh lihat detail izin
        ];
        $roleKeuangan->syncPermissions($keuanganPermissions);


        // Teller (CONTOH: Teller TIDAK punya akses ke roles/permissions)
        $tellerPermissions = [
            'view_filament_admin_panel',
            'create_users', 'view_any_users', 'view_users', 'update_users',
            'create_savings', 'confirm_savings', 'view_any_savings', 'view_savings', 'update_savings',
            'create_payments', 'view_any_payments', 'view_payments', 'update_payments',
            'view_any_loans', 'view_loans',
        ];
        $roleTeller->syncPermissions($tellerPermissions);


        // Anggota (CONTOH: Anggota TIDAK punya akses ke roles/permissions)
        $anggotaPermissions = [
            'view_filament_admin_panel',
            'view_own_savings', 'view_savings',
            'create_loans',
            'view_own_loans', 'view_loans',
            'view_own_payments', 'view_payments',
            'view_users',
        ];
        $roleAnggota->syncPermissions($anggotaPermissions);
    }
}