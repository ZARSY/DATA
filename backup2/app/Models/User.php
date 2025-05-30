<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles; // <-- BARIS INI PENTING
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser // Pastikan implements FilamentUser
{
    use HasFactory, Notifiable, HasRoles; // <-- TAMBAHKAN HasRoles DI SINI

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'nomor_anggota', // <-- TAMBAHKAN
        'alamat',       // <-- TAMBAHKAN
        'telepon',      // <-- TAMBAHKAN
         // Tambahkan ini agar bisa diisi saat create user admin awal
        // Kolom 'role' manual TIDAK kita gunakan lagi.
        // Kolom kustom seperti 'nomor_anggota' akan kita tambahkan di migrasi selanjutnya.
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Method dari FilamentUser
    public function canAccessPanel(Panel $panel): bool
    {
        // Untuk sekarang, semua user yang terautentikasi boleh masuk.
        // Pembatasan akses ke resource diatur oleh Policy dan Spatie.
        return true;
    }
}