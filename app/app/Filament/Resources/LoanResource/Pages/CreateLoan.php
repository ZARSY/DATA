<?php

namespace App\Filament\Resources\LoanResource\Pages;

use App\Filament\Resources\LoanResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth; // <-- TAMBAHKAN IMPORT INI
use App\Models\Loan; //

class CreateLoan extends CreateRecord
{
    protected static string $resource = LoanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $loggedInUser = Auth::user();

        if ($loggedInUser && $loggedInUser->hasRole('Anggota')) {
            $data['user_id'] = $loggedInUser->id;
        }
        // Jika Admin/Teller yang create, 'user_id' harus sudah dipilih di form

        $data['status'] = 'diajukan';
        $data['tanggal_persetujuan'] = null;

        // PERBAIKAN DI SINI:
        // Pastikan bunga_persen_per_bulan memiliki nilai default jika kosong/null.
        // Anggota tidak mengisi ini, jadi kita set default 0.
        // Manajer Keuangan akan mengisinya saat proses persetujuan.
        $data['bunga_persen_per_bulan'] = $data['bunga_persen_per_bulan'] ?? 0.00; // Set default 0

        $data['approved_by'] = null;
        $data['keterangan_approval'] = null;
        $data['tanggal_pengajuan'] = $data['tanggal_pengajuan'] ?? now()->format('Y-m-d');

        return $data;
}}
