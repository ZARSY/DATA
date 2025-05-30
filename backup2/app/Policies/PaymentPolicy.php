<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    /**
     * Determine whether the user can view any models.
     * Admin, Teller, Manajer Keuangan: Bisa lihat daftar semua angsuran.
     * Anggota: Bisa lihat menu angsuran (datanya akan difilter).
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_payments') || $user->can('view_own_payments');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Payment $payment): bool
    {
        if ($user->hasRole('Anggota')) {
            // Pastikan relasi 'loan' dan 'loan->user_id' ada dan benar
            if ($payment->loan && $payment->loan->user_id === $user->id) {
                return $user->can('view_own_payments');
            }
            return false;
        }
        return $user->can('view_payments'); // Untuk Admin, Teller, Keuangan
    }

    /**
     * Determine whether the user can create models.
     * Teller: Bisa input angsuran. Admin juga.
     */
    public function create(User $user): bool
    {
        return $user->can('create_payments');
    }

    /**
     * Determine whether the user can update the model.
     * Biasanya angsuran jarang diupdate, mungkin hanya Admin.
     */
    public function update(User $user, Payment $payment): bool
    {
        return $user->can('update_payments');
    }

    /**
     * Determine whether the user can delete the model.
     * Hanya Admin.
     */
    public function delete(User $user, Payment $payment): bool
    {
        return $user->can('delete_payments');
    }

    // public function restore(User $user, Payment $payment): bool
    // {
    //     return $user->can('delete_payments');
    // }

    // public function forceDelete(User $user, Payment $payment): bool
    // {
    //     return $user->can('delete_payments');
    // }
}