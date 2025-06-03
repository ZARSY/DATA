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
        // Permission dasar untuk akses panel
        Permission::findOrCreate('view_filament_admin_panel', 'web');
        Permission::findOrCreate('view_financial_dashboard_widget', 'web');

        // Permissions untuk akses ke halaman Dashboard
        Permission::findOrCreate('view_financial_reports', 'web');

        // User Management
        Permission::findOrCreate('view_any_users', 'web');
        Permission::findOrCreate('view_users', 'web');
        Permission::findOrCreate('create_users', 'web'); // Untuk Admin
        Permission::findOrCreate('create_members', 'web'); // Untuk Teller mendaftarkan Anggota
        Permission::findOrCreate('update_users', 'web');
        Permission::findOrCreate('delete_users', 'web');
        Permission::findOrCreate('assign_user_roles', 'web'); // Hanya Admin

        // Savings
        Permission::findOrCreate('view_any_savings', 'web');
        Permission::findOrCreate('view_savings', 'web');
        Permission::findOrCreate('create_savings', 'web');
        Permission::findOrCreate('update_savings', 'web');
        Permission::findOrCreate('delete_savings', 'web');
        Permission::findOrCreate('confirm_savings', 'web');
        Permission::findOrCreate('view_own_savings', 'web');

        // Loans
        Permission::findOrCreate('view_any_loans', 'web');
        Permission::findOrCreate('view_loans', 'web');
        Permission::findOrCreate('create_loans', 'web'); // Untuk Anggota (apply) & Admin
        Permission::findOrCreate('update_loans', 'web');
        Permission::findOrCreate('delete_loans', 'web');
        Permission::findOrCreate('approve_loans', 'web'); // Untuk Manajer Keuangan
        Permission::findOrCreate('view_own_loans', 'web');

        // Payments
        Permission::findOrCreate('view_any_payments', 'web');
        Permission::findOrCreate('view_payments', 'web');
        Permission::findOrCreate('create_payments', 'web');
        Permission::findOrCreate('update_payments', 'web');
        Permission::findOrCreate('delete_payments', 'web');
        Permission::findOrCreate('view_own_payments', 'web');
        Permission::findOrCreate('confirm_payments', 'web');

        // Permissions untuk mengelola Roles (AKSES KHUSUS)
        Permission::findOrCreate('view_any_roles', 'web');
        Permission::findOrCreate('view_roles', 'web');
        Permission::findOrCreate('create_roles', 'web');    // Admin
        Permission::findOrCreate('update_roles', 'web');    // Admin
        Permission::findOrCreate('delete_roles', 'web');    // Admin
        Permission::findOrCreate('assign_permissions_to_role', 'web'); // Admin

        // Permissions untuk mengelola Permissions (AKSES KHUSUS)
        Permission::findOrCreate('view_any_permissions', 'web');
        Permission::findOrCreate('view_permissions', 'web');
        // create, update, delete permissions biasanya tidak dari UI


        // === ROLES ===
        $roleAdmin = Role::findOrCreate('Admin', 'web');
        $roleTeller = Role::findOrCreate('Teller', 'web');
        $roleKeuangan = Role::findOrCreate('Manajer Keuangan', 'web');
        $roleAnggota = Role::findOrCreate('Anggota', 'web');


        // === MENETAPKAN PERMISSIONS KE ROLES ===

        // Admin: Mendapatkan SEMUA permission yang telah kita definisikan di atas
        // Ini cara aman untuk memastikan semua permission baru juga didapat Admin.
        $allPermissions = Permission::all();
        $roleAdmin->syncPermissions($allPermissions);


        // Manajer Keuangan:
        $keuanganPermissions = [
            'view_filament_admin_panel',
            'approve_loans', 'view_any_loans', 'view_loans', 'update_loans', // Bisa update loan untuk status berjalan, dll.
            'view_any_savings', 'view_savings',
            'view_any_payments', 'view_payments',
            'view_any_users', 'view_users', 'view_financial_reports', // Bisa lihat daftar user/anggota

            // Akses untuk melihat Roles dan Permissions
            'view_any_roles',
            'view_roles',
            'view_any_permissions',
            'view_permissions',
            'confirm_payments',
            'view_financial_dashboard_widget',
             // Untuk mengonfirmasi pembayaran
            // JANGAN berikan create/update/delete roles/permissions kepada Manajer Keuangan kecuali memang diinginkan
            // 'create_roles',
            // 'update_roles',
            // 'delete_roles',
            // 'assign_permissions_to_role',
        ];
        $roleKeuangan->syncPermissions($keuanganPermissions);


        // Teller: TIDAK punya akses ke roles/permissions menu
        $tellerPermissions = [
            'view_filament_admin_panel',
            'create_members',       // Menggunakan permission ini untuk membedakan dari create_users oleh Admin
            'view_any_users',       // Teller mungkin perlu lihat daftar anggota untuk memilih
            'view_users',
            'update_users',         // Teller bisa update data Anggota
            'create_savings', 'confirm_savings', 'view_any_savings', 'view_savings', 'update_savings',
            'create_payments', 'view_any_payments', 'view_payments', 'update_payments',
            'view_any_loans', 'view_loans', 'approve_loans', // Untuk referensi saat input angsuran
        ];
        $roleTeller->syncPermissions($tellerPermissions);


        // Anggota: TIDAK punya akses ke roles/permissions menu
        $anggotaPermissions = [
            'view_filament_admin_panel',
            'view_own_savings', 'view_savings', // view_savings agar bisa lihat detail miliknya
            'create_loans',                   // Untuk mengajukan pinjaman
            'view_own_loans', 'view_loans',   // view_loans agar bisa lihat detail miliknya
            'view_own_payments', 'view_payments', // view_payments agar bisa lihat detail miliknya
            'view_users',
            'create_savings',                     // Untuk melihat profilnya sendiri melalui UserResource (jika diizinkan policy)
        ];
        $roleAnggota->syncPermissions($anggotaPermissions);
    }
}