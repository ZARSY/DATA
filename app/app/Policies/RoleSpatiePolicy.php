<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RoleSpatiePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Hanya Admin atau Manajer Keuangan yang bisa lihat daftar Roles
        // dan mereka harus punya permission 'view_any_roles'
        return ($user->hasRole('Admin') || $user->hasRole('Manajer Keuangan')) && $user->can('view_any_roles');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Role $role): bool
    {
        return ($user->hasRole('Admin') || $user->hasRole('Manajer Keuangan')) && $user->can('view_roles');
    }

    /**
     * Determine whether the user can create models.
     * Biasanya hanya Admin yang boleh membuat role baru.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('Admin') && $user->can('create_roles');
    }

    /**
     * Determine whether the user can update the model.
     * Admin atau Manajer Keuangan (jika diberi izin) bisa update role.
     * Berhati-hatilah dengan mengizinkan update role 'Admin'.
     */
    public function update(User $user, Role $role): bool
    {
        // Contoh: Hanya Admin yang bisa update semua, Manajer Keuangan mungkin tidak
        return $user->hasRole('Admin') && $user->can('update_roles');
        // Jika Manajer Keuangan juga boleh:
        // return ($user->hasRole('Admin') || $user->hasRole('Manajer Keuangan')) && $user->can('update_roles');
    }

    /**
     * Determine whether the user can delete the model.
     * Hanya Admin, dan hindari menghapus role inti.
     */
    public function delete(User $user, Role $role): bool
    {
        if (in_array($role->name, ['Admin', 'Teller', 'Manajer Keuangan', 'Anggota'])) {
            return false; // Jangan biarkan role inti dihapus
        }
        return $user->hasRole('Admin') && $user->can('delete_roles');
    }

    /**
     * Determine whether the user can assign permissions to role.
     * Hanya Admin.
     */
    public function assignPermissionsToRole(User $user, Role $role): bool
    {
        return $user->hasRole('Admin') && $user->can('assign_permissions_to_role');
    }
}