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
        app()[PermissionRegistrar::class]->forgetCachedPermissions(); // Reset cache

        // --- DEFINISI ROLES ---
        $roleAdmin = Role::create(['name' => 'Admin']);
        $roleTeller = Role::create(['name' => 'Teller']);
        $roleKeuangan = Role::create(['name' => 'Manajer Keuangan']);
        $roleAnggota = Role::create(['name' => 'Anggota']);

        // --- DEFINISI PERMISSIONS DASAR (CRUD akan banyak di-generate Shield) ---
        // User Management
        Permission::create(['name' => 'manage users']); // Admin
        Permission::create(['name' => 'create members']); // Teller (untuk mendaftarkan anggota)
        Permission::create(['name' => 'view members']); // Teller, Keuangan

        // Savings
        Permission::create(['name' => 'manage savings']); // Admin
        Permission::create(['name' => 'create savings entries']); // Teller
        Permission::create(['name' => 'confirm savings entries']); // Teller/Admin
        Permission::create(['name' => 'view all savings']); // Admin, Teller, Keuangan
        Permission::create(['name' => 'view own savings']); // Anggota

        // Loans
        Permission::create(['name' => 'manage loans']); // Admin
        Permission::create(['name' => 'apply for loan']); // Anggota
        Permission::create(['name' => 'approve or reject loans']); // Manajer Keuangan
        Permission::create(['name' => 'view all loans']); // Admin, Teller, Keuangan
        Permission::create(['name' => 'view own loans']); // Anggota

        // Payments
        Permission::create(['name' => 'manage payments']); // Admin
        Permission::create(['name' => 'create payment entries']); // Teller
        Permission::create(['name' => 'view all payments']); // Admin, Teller, Keuangan
        Permission::create(['name' => 'view own payments']); // Anggota

        // Role & Permission Management (untuk Filament Shield)
        Permission::create(['name' => 'manage roles']);
        Permission::create(['name' => 'manage permissions']);


        // --- ASSIGN PERMISSIONS KE ROLES ---
        $roleAdmin->givePermissionTo(Permission::all()); // Admin dapat semua

        $roleTeller->givePermissionTo([
            'create members', 'view members',
            'create savings entries', 'confirm savings entries', 'view all savings',
            'create payment entries', 'view all payments',
            'view all loans', // Untuk referensi
        ]);

        $roleKeuangan->givePermissionTo([
            'approve or reject loans', 'view all loans',
            'view all savings', 'view all payments', 'view members',
        ]);

        $roleAnggota->givePermissionTo([
            'apply for loan',
            'view own savings', 'view own loans', 'view own payments',
        ]);
    }
}