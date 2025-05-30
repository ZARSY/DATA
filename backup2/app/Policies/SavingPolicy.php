<?php

namespace App\Policies;

use App\Models\Saving;
use App\Models\User;

class SavingPolicy
{
    /**
     * Determine whether the user can view any models.
     * Admin, Teller, Manajer Keuangan: Bisa lihat daftar semua simpanan.
     * Anggota: Bisa lihat menu simpanan (datanya akan difilter di Resource).
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_savings') || $user->can('view_own_savings');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Saving $saving): bool
    {
        if ($user->hasRole('Anggota')) {
            return $saving->user_id === $user->id && $user->can('view_own_savings');
        }
        return $user->can('view_savings'); // Untuk Admin, Teller, Keuangan
    }

    /**
     * Determine whether the user can create models.
     * Teller: Bisa input simpanan. Admin juga.
     */
    public function create(User $user): bool
    {
        return $user->can('create_savings');
    }

    /**
     * Determine whether the user can update the model.
     * Teller: Mungkin bisa update jika status pending. Admin bisa update.
     */
    public function update(User $user, Saving $saving): bool
    {
        if ($user->hasRole('Teller') && $saving->status !== 'pending' && !$user->can('update_confirmed_savings')) { // Contoh permission tambahan
            return false;
        }
        return $user->can('update_savings');
    }

    /**
     * Determine whether the user can delete the model.
     * Hanya Admin.
     */
    public function delete(User $user, Saving $saving): bool
    {
        return $user->can('delete_savings');
    }

    /**
     * Determine whether the user can confirm the saving.
     * Teller & Admin.
     */
    public function confirm(User $user, Saving $saving): bool
    {
        return $user->can('confirm_savings') && $saving->status === 'pending';
    }

    // public function restore(User $user, Saving $saving): bool
    // {
    //     return $user->can('delete_savings');
    // }

    // public function forceDelete(User $user, Saving $saving): bool
    // {
    //     return $user->can('delete_savings');
    // }
}