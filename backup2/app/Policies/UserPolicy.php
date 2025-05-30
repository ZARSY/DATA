<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response; // Bisa digunakan untuk pesan custom, tapi boolean cukup

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     * Admin: Bisa lihat semua user.
     * Teller: Bisa lihat daftar anggota (jika kita filter di UserResource query).
     * Manajer Keuangan: Bisa lihat daftar anggota (jika kita filter di UserResource query).
     * Anggota: Tidak bisa lihat daftar semua user.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_users') || $user->can('view_members');
    }

    /**
     * Determine whether the user can view the model.
     * Semua user bisa lihat profilnya sendiri.
     * Admin, Teller, Manajer Keuangan bisa lihat detail user lain (terutama Anggota).
     */
    public function view(User $user, User $modelToView): bool
    {
        if ($user->id === $modelToView->id) {
            return true; // Selalu bisa lihat profil sendiri
        }
        return $user->can('view_users');
    }

    /**
     * Determine whether the user can create models.
     * Admin: Bisa buat semua jenis user.
     * Teller: Bisa buat user dengan role Anggota.
     */
    public function create(User $user): bool
    {
        // Di seeder, 'create_users' diberikan ke Admin, 'create_members' ke Teller
        return $user->can('create_users') || $user->can('create_members');
    }

    /**
     * Determine whether the user can update the model.
     * Admin: Bisa update semua user.
     * Teller: Bisa update data Anggota.
     * Anggota: Bisa update profilnya sendiri (nama, email, password - dihandle halaman profil Filament).
     * Jika Anggota update via UserResource, pastikan field yang boleh diubah terbatas.
     */
    public function update(User $user, User $modelToUpdate): bool
    {
        if ($user->id === $modelToUpdate->id) {
            // Anggota bisa update profilnya sendiri (misal via halaman Profile Filament).
            // Jika Anggota mencoba edit dirinya via UserResource, pastikan hanya field non-sensitif.
            // Untuk amannya, kita bisa berikan permission 'update_own_profile' jika ingin spesifik.
            // Atau kita asumsikan jika dia bisa 'update_users', itu juga berlaku untuk dirinya.
            return true; // Izinkan update diri sendiri (Filament profile page akan pakai ini)
        }

        // Teller bisa update Anggota jika punya permission 'update_users' dan targetnya adalah Anggota
        if ($user->hasRole('Teller') && $modelToUpdate->hasRole('Anggota')) {
            return $user->can('update_users');
        }

        return $user->can('update_users'); // Umumnya untuk Admin
    }

    /**
     * Determine whether the user can delete the model.
     * Hanya Admin yang bisa hapus user, dan tidak bisa hapus diri sendiri.
     */
    public function delete(User $user, User $modelToDelete): bool
    {
        if ($user->id === $modelToDelete->id) {
            return false; // Tidak bisa hapus diri sendiri
        }
        return $user->can('delete_users');
    }

    /**
     * Determine whether the user can assign roles.
     * Hanya Admin.
     */
    public function assignUserRoles(User $user, User $modelToAssign): bool
    {
        return $user->can('assign_user_roles');
    }

    // public function restore(User $user, User $model): bool
    // {
    //     return $user->can('delete_users'); // Atau permission terpisah 'restore_users'
    // }

    // public function forceDelete(User $user, User $model): bool
    // {
    //     return $user->can('delete_users'); // Atau permission terpisah 'force_delete_users'
    // }
}