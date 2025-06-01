<?php

namespace App\Policies;

use App\Models\Saving;
use App\Models\User;
// use Illuminate\Auth\Access\HandlesAuthorization; // Tidak perlu di Laravel 11 style policy class

class SavingPolicy
{
    // Jika Anda ingin Admin bisa melakukan segalanya tanpa perlu dicek permission spesifik di policy ini:
    // public function before(User $user, string $ability): bool|null
    // {
    //     if ($user->hasRole('Admin')) {
    //         // Hati-hati, ini akan meng-override semua pengecekan di bawah untuk Admin
    //         // Pertimbangkan apakah ini sesuai dengan kebutuhan keamanan Anda.
    //         // return true;
    //     }
    //     return null; // Lanjutkan ke method policy lainnya
    // }

    /**
     * Determine whether the user can view any models.
     * Admin, Teller, Manajer Keuangan: Bisa lihat daftar semua simpanan jika punya permission.
     * Anggota: Bisa lihat menu simpanan jika punya permission (datanya akan difilter di Resource).
     */
    public function viewAny(User $user): bool
    {
        // Pengguna bisa melihat daftar jika punya izin umum ATAU izin lihat milik sendiri
        return $user->can('view_any_savings') || $user->can('view_own_savings');
    }

    /**
     * Determine whether the user can view the model.
     * Admin, Teller, Manajer Keuangan: Bisa lihat detail jika punya permission.
     * Anggota: Bisa lihat detail simpanan miliknya sendiri jika punya permission.
     */
    public function view(User $user, Saving $saving): bool
    {
        if ($user->hasRole('Anggota')) {
            // Anggota hanya boleh lihat simpanannya sendiri DAN harus punya permission yang sesuai
            return $saving->user_id === $user->id && $user->can('view_own_savings');
        }
        // Role lain (Admin, Teller, Manajer Keuangan) harus punya permission untuk melihat detail simpanan
        return $user->can('view_savings');
    }

    /**
     * Determine whether the user can create models.
     * Teller atau Admin bisa input simpanan jika punya permission.
     */
    public function create(User $user): bool
    {
        return $user->can('create_savings');
    }

    /**
     * Determine whether the user can update the model.
     * Admin bisa update jika punya permission.
     * Teller mungkin bisa update jika statusnya masih 'pending_approval' atau 'pending'.
     */
    public function update(User $user, Saving $saving): bool
    {
        // Admin bisa update jika punya permission 'update_savings'
        if ($user->hasRole('Admin') && $user->can('update_savings')) {
            return true;
        }

        // Teller bisa update jika punya permission 'update_savings' DAN
        // status simpanan masih dalam tahap awal (belum dikonfirmasi atau ditolak)
        if ($user->hasRole('Teller') && $user->can('update_savings')) {
            if (in_array($saving->status, ['pending_approval', 'pending'])) {
                return true;
            }
            // Opsional: Jika Teller boleh update yang sudah dikonfirmasi dengan permission khusus
            // if ($saving->status === 'dikonfirmasi' && $user->can('update_confirmed_savings')) {
            //     return true;
            // }
        }
        return false; // Selain kondisi di atas, tidak diizinkan
    }

    /**
     * Determine whether the user can delete the model.
     * Biasanya hanya Admin.
     */
    public function delete(User $user, Saving $saving): bool
    {
        // Hanya user dengan permission 'delete_savings' (biasanya Admin)
        // dan mungkin ada kondisi tambahan, misal simpanan belum pernah ada transaksi terkait
        return $user->can('delete_savings');
    }

    /**
     * Determine whether the user can confirm or reject the saving.
     * Method ini akan digunakan oleh aksi "Setujui" dan "Tolak" di Resource.
     * Teller & Admin.
     */
    public function confirmOrReject(User $user, Saving $saving): bool // Nama method lebih generik
    {
        // User bisa melakukan aksi ini jika punya permission 'confirm_savings'
        // DAN status simpanan adalah 'pending_approval' atau 'pending' (untuk data lama)
        return $user->can('confirm_savings') && in_array($saving->status, ['pending_approval', 'pending']);
    }

    // Jika Anda ingin memisahkan logika untuk 'reject', Anda bisa buat method sendiri:
    // public function reject(User $user, Saving $saving): bool
    // {
    //     return $this->confirmOrReject($user, $saving); // Atau logika yang sedikit berbeda jika perlu
    // }

    // public function restore(User $user, Saving $saving): bool
    // {
    //     // Biasanya yang bisa delete, bisa restore jika menggunakan SoftDeletes
    //     return $user->can('delete_savings'); // Atau permission terpisah 'restore_savings'
    // }

    // public function forceDelete(User $user, Saving $saving): bool
    // {
    //     // Biasanya yang bisa delete, bisa force delete jika menggunakan SoftDeletes
    //     return $user->can('delete_savings'); // Atau permission terpisah 'force_delete_savings'
    // }
}