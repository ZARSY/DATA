<?php

namespace App\Policies;

use App\Models\Loan;
use App\Models\User;

class LoanPolicy
{
    /**
     * Determine whether the user can view any models.
     * Admin, Teller, Manajer Keuangan: Bisa lihat daftar semua pinjaman.
     * Anggota: Bisa lihat menu pinjaman (datanya akan difilter).
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_loans') || $user->can('view_own_loans');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Loan $loan): bool
    {
        if ($user->hasRole('Anggota')) {
            return $loan->user_id === $user->id && $user->can('view_own_loans');
        }
        return $user->can('view_loans'); // Untuk Admin, Teller, Keuangan
    }

    /**
     * Determine whether the user can create models.
     * Anggota: Bisa mengajukan pinjaman. Admin mungkin bisa input pinjaman atas nama anggota.
     */
    public function create(User $user): bool
    {
        // 'create_loans' di seeder kita berikan ke Anggota (untuk apply) dan Admin
        return $user->can('create_loans');
    }

    /**
     * Determine whether the user can update the model.
     * Manajer Keuangan: Bisa update status (approve/reject). Admin bisa edit detail.
     */
    public function update(User $user, Loan $loan): bool
    {
        // Manajer Keuangan bisa update untuk approval jika statusnya masih diajukan
        if ($user->can('approve_loans') && $loan->status === 'diajukan') {
            return true;
        }
        // Admin bisa update lebih luas
        return $user->can('update_loans');
    }

    /**
     * Determine whether the user can delete the model.
     * Hanya Admin.
     */
    public function delete(User $user, Loan $loan): bool
    {
        return $user->can('delete_loans');
    }

    /**
     * Custom method untuk aksi approve/reject.
     * Manajer Keuangan.
     */
    public function approveOrReject(User $user, Loan $loan): bool
    {
        return $user->can('approve_loans') && $loan->status === 'diajukan';
    }


    // public function restore(User $user, Loan $loan): bool
    // {
    //     return $user->can('delete_loans');
    // }

    // public function forceDelete(User $user, Loan $loan): bool
    // {
    //     return $user->can('delete_loans');
    // }
}