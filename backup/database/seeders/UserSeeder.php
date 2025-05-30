<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Hapus user admin awal jika ada, agar tidak duplikat jika seeder dijalankan berkali-kali
        User::where('email', 'superadmin@example.com')->delete();
        User::where('email', 'admin@simpin.dev')->delete();
        User::where('email', 'teller@simpin.dev')->delete();
        User::where('email', 'keuangan@simpin.dev')->delete();
        User::where('email', 'budi@simpin.dev')->delete();
        User::where('email', 'siti@simpin.dev')->delete();


        $admin = User::create([
            'name' => 'Admin Utama',
            'email' => 'admin@simpin.dev',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'nomor_anggota' => 'ADM001',
        ]);
        $admin->assignRole('Admin');

        $teller = User::create([
            'name' => 'Teller Cepat',
            'email' => 'teller@simpin.dev',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'nomor_anggota' => 'TLR001',
        ]);
        $teller->assignRole('Teller');

        $keuangan = User::create([
            'name' => 'Manajer Finansial',
            'email' => 'keuangan@simpin.dev',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'nomor_anggota' => 'KEU001',
        ]);
        $keuangan->assignRole('Manajer Keuangan');

        $anggota1 = User::create([
            'name' => 'Budi Setiawan',
            'email' => 'budi@simpin.dev',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'nomor_anggota' => 'AGT001', 'alamat' => 'Jl. Satu No. 1', 'telepon' => '081111',
        ]);
        $anggota1->assignRole('Anggota');

        $anggota2 = User::create([
            'name' => 'Siti Lestari',
            'email' => 'siti@simpin.dev',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'nomor_anggota' => 'AGT002', 'alamat' => 'Jl. Dua No. 2', 'telepon' => '082222',
        ]);
        $anggota2->assignRole('Anggota');
    }
}