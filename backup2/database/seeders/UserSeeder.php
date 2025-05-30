<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User; // Pastikan path ke model User Anda benar
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Untuk menghindari error jika seeder dijalankan berkali-kali,
        // Anda bisa menambahkan pengecekan atau menghapus user lama dulu.
        // Contoh sederhana menghapus berdasarkan email (hati-hati jika email bisa sama untuk user beda)
        User::whereIn('email', [
            'admin@simpin.test',
            'teller@simpin.test',
            'keuangan@simpin.test',
            'budi@simpin.test',
            'siti@simpin.test'
        ])->delete();

        // Membuat User Admin
        $admin = User::create([
            'name' => 'Administrator Aplikasi',
            'email' => 'admin@simpin.test',
            'password' => Hash::make('password'), // Ganti dengan password yang aman
            'email_verified_at' => now(),
            'nomor_anggota' => 'ADM001',
            'alamat' => 'Kantor Pusat',
            'telepon' => '0000000000',
        ]);
        $admin->assignRole('Admin'); // Menetapkan role 'Admin'

        // Membuat User Teller
        $teller = User::create([
            'name' => 'Staff Teller',
            'email' => 'teller@simpin.test',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'nomor_anggota' => 'TLR001',
            'alamat' => 'Meja Layanan',
            'telepon' => '0000000001',
        ]);
        $teller->assignRole('Teller');

        // Membuat User Manajer Keuangan
        $keuangan = User::create([
            'name' => 'Manajer Keuangan',
            'email' => 'keuangan@simpin.test',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'nomor_anggota' => 'KEU001',
            'alamat' => 'Ruang Keuangan',
            'telepon' => '0000000002',
        ]);
        $keuangan->assignRole('Manajer Keuangan');

        // Membuat User Anggota 1
        $anggota1 = User::create([
            'name' => 'Budi Sanjaya',
            'email' => 'budi@simpin.test',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'nomor_anggota' => 'AGT001',
            'alamat' => 'Jl. Anggrek No. 1, Sidoarjo',
            'telepon' => '08123456001',
        ]);
        $anggota1->assignRole('Anggota');

        // Membuat User Anggota 2
        $anggota2 = User::create([
            'name' => 'Siti Rahmawati',
            'email' => 'siti@simpin.test',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'nomor_anggota' => 'AGT002',
            'alamat' => 'Jl. Mawar No. 2, Surabaya',
            'telepon' => '08123456002',
        ]);
        $anggota2->assignRole('Anggota');
    }
}