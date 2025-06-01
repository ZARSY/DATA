<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Permission;

class PermissionSpatiePolicy
{
    public function viewAny(User $user): bool
    {
        // Admin atau Manajer Keuangan bisa lihat daftar Permissions jika punya permission 'view_any_permissions'
        return ($user->hasRole('Admin') || $user->hasRole('Manajer Keuangan')) && $user->can('view_any_permissions');
    }

    public function view(User $user, Permission $permission): bool
    {
        return ($user->hasRole('Admin') || $user->hasRole('Manajer Keuangan')) && $user->can('view_permissions');
    }

    public function create(User $user): bool { return false; } // Biasanya tidak dari UI
    public function update(User $user, Permission $permission): bool { return false; } // Biasanya tidak dari UI
    public function delete(User $user, Permission $permission): bool { return false; } // Biasanya tidak dari UI
}