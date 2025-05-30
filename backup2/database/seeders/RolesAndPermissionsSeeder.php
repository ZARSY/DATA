<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar; // Penting untuk reset cache

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // === DEFINISI PERMISSIONS ===
        // Format umum: aksi_entitas (misal: create_savings, view_loans)
        // Beberapa permission umum yang mungkin digunakan oleh paket UI atau untuk kemudahan
        Permission::findOrCreate('view_filament_admin_panel', 'web'); // Izin dasar untuk akses panel

        // Permissions untuk User Management (Anggota)
        Permission::findOrCreate('view_any_users', 'web');      // Melihat daftar semua user
        Permission::findOrCreate('view_users', 'web');          // Melihat detail user
        Permission::findOrCreate('create_users', 'web');        // Membuat user baru (termasuk anggota oleh Teller/Admin)
        Permission::findOrCreate('update_users', 'web');        // Mengubah data user
        Permission::findOrCreate('delete_users', 'web');        // Menghapus user
        Permission::findOrCreate('assign_user_roles', 'web');   // Menetapkan role ke user (biasanya Admin)

        // Permissions untuk Simpanan (Savings)
        Permission::findOrCreate('view_any_savings', 'web');
        Permission::findOrCreate('view_savings', 'web');
        Permission::findOrCreate('create_savings', 'web');        // Teller input simpanan
        Permission::findOrCreate('update_savings', 'web');
        Permission::findOrCreate('delete_savings', 'web');
        Permission::findOrCreate('confirm_savings', 'web');       // Teller/Admin konfirmasi simpanan
        Permission::findOrCreate('view_own_savings', 'web');      // Anggota lihat simpanan sendiri

        // Permissions untuk Pinjaman (Loans)
        Permission::findOrCreate('view_any_loans', 'web');
        Permission::findOrCreate('view_loans', 'web');
        Permission::findOrCreate('create_loans', 'web');        // Anggota mengajukan pinjaman (atau Admin input)
        Permission::findOrCreate('update_loans', 'web');        // Misal: Admin edit detail, Manajer Keuangan update status
        Permission::findOrCreate('delete_loans', 'web');
        Permission::findOrCreate('approve_loans', 'web');       // Manajer Keuangan menyetujui/menolak
        Permission::findOrCreate('view_own_loans', 'web');        // Anggota lihat pinjaman sendiri

        // Permissions untuk Angsuran (Payments)
        Permission::findOrCreate('view_any_payments', 'web');
        Permission::findOrCreate('view_payments', 'web');
        Permission::findOrCreate('create_payments', 'web');       // Teller input angsuran
        Permission::findOrCreate('update_payments', 'web');
        Permission::findOrCreate('delete_payments', 'web');
        Permission::findOrCreate('view_own_payments', 'web');     // Anggota lihat angsuran sendiri

        // Permissions untuk mengelola Roles & Permissions itu sendiri (jika paket UI butuh ini)
        Permission::findOrCreate('view_any_roles', 'web');
        Permission::findOrCreate('view_roles', 'web');
        Permission::findOrCreate('create_roles', 'web');
        Permission::findOrCreate('update_roles', 'web');
        Permission::findOrCreate('delete_roles', 'web');
        Permission::findOrCreate('assign_permissions_to_role', 'web');


        // === DEFINISI ROLES ===
        // Variabel ini akan digunakan di bawahnya
        $roleAdmin = Role::findOrCreate('Admin', 'web');
        $roleTeller = Role::findOrCreate('Teller', 'web');
        $roleKeuangan = Role::findOrCreate('Manajer Keuangan', 'web');
        $roleAnggota = Role::findOrCreate('Anggota', 'web');


        // === MENETAPKAN PERMISSIONS KE ROLES ===

        // Admin mendapatkan semua izin (untuk kemudahan development, di produksi lebih baik spesifik)
        // Jika ingin spesifik, list semua permission yang sudah dibuat di atas.
        $roleAdmin->givePermissionTo(Permission::all()); // <--- Variabel $roleAdmin digunakan di sini

        // Teller
        $roleTeller->givePermissionTo([ // <--- Variabel $roleTeller digunakan di sini
            'view_filament_admin_panel',
            'create_users',
            'view_any_users', 'view_users',
            'update_users',
            'create_savings', 'confirm_savings', 'view_any_savings', 'view_savings', 'update_savings',
            'create_payments', 'view_any_payments', 'view_payments', 'update_payments',
            'view_any_loans', 'view_loans',
        ]);

        // Manajer Keuangan
        $roleKeuangan->givePermissionTo([ // <--- Variabel $roleKeuangan digunakan di sini
            'view_filament_admin_panel',
            'approve_loans', 'view_any_loans', 'view_loans', 'update_loans',
            'view_any_savings', 'view_savings',
            'view_any_payments', 'view_payments',
            'view_any_users', 'view_users',
        ]);

        // Anggota
        $roleAnggota->givePermissionTo([ // <--- Variabel $roleAnggota digunakan di sini
            'view_filament_admin_panel',
            'view_own_savings', 'view_savings',
            'create_loans',
            'view_own_loans', 'view_loans',
            'view_own_payments', 'view_payments',
            'view_users',
        ]);
    }
}