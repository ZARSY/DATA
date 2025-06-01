<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RoleSpatiePolicy
{
    public function viewAny(User $user): bool
    {
        // Admin atau Manajer Keuangan bisa lihat daftar Roles jika punya permission 'view_any_roles'
        return ($user->hasRole('Admin') || $user->hasRole('Manajer Keuangan')) && $user->can('view_any_roles');
    }

    public function view(User $user, Role $role): bool
    {
        return ($user->hasRole('Admin') || $user->hasRole('Manajer Keuangan')) && $user->can('view_roles');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Admin') && $user->can('create_roles'); // Hanya Admin
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasRole('Admin') && $user->can('update_roles'); // Hanya Admin
    }

    public function delete(User $user, Role $role): bool
    {
        if (in_array($role->name, ['Admin', 'Teller', 'Manajer Keuangan', 'Anggota'])) {
            return false; // Role inti tidak boleh dihapus
        }
        return $user->hasRole('Admin') && $user->can('delete_roles'); // Hanya Admin
    }

    public function assignPermissionsToRole(User $user, Role $role): bool // Custom method untuk UI
    {
        return $user->hasRole('Admin') && $user->can('assign_permissions_to_role'); // Hanya Admin
    }
}