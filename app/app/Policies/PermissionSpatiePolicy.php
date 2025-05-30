<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Permission;

class PermissionSpatiePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Hanya Admin atau Manajer Keuangan yang bisa lihat daftar permission
        return ($user->hasRole('Admin') || $user->hasRole('Manajer Keuangan')) && $user->can('view_any_permissions');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Permission $permission): bool
    {
        return ($user->hasRole('Admin') || $user->hasRole('Manajer Keuangan')) && $user->can('view_permissions');
    }

    // Method create, update, delete untuk permissions biasanya false
    // karena permissions lebih baik didefinisikan di kode/seeder.
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Permission $permission): bool
    {
        return false;
    }

    public function delete(User $user, Permission $permission): bool
    {
        return false;
    }
}