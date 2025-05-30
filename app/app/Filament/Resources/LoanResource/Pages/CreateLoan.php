<?php

namespace App\Filament\Resources\LoanResource\Pages;

use App\Filament\Resources\LoanResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth; // <-- PASTIKAN IMPORT INI ADA
// use App\Models\Loan; // Tidak wajib untuk file ini, tapi bisa untuk type hinting

class CreateLoan extends CreateRecord
{
    protected static string $resource = LoanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $loggedInUser = Auth::user();

        // Jika yang membuat adalah Anggota, pastikan user_id adalah ID-nya
        if ($loggedInUser && $loggedInUser->hasRole('Anggota')) {
            $data['user_id'] = $loggedInUser->id;
        }
        // Jika Admin atau Teller yang membuat, 'user_id' harus sudah dipilih dari form.
        // Validasi ->required() di LoanResource::form() akan menangani jika Admin/Teller lupa memilih.

        // Selalu set status 'diajukan' saat membuat record baru dari form ini.
        $data['status'] = 'diajukan';

        // Pastikan field persetujuan dan bunga di-set ke NULL atau nilai default yang aman
        // karena ini baru tahap pengajuan oleh Anggota, atau input awal oleh Admin/Teller.
        $data['tanggal_persetujuan'] = null;
        $data['bunga_persen_per_bulan'] = $data['bunga_persen_per_bulan'] ?? 0.00; // Default 0 untuk bunga saat pengajuan
        $data['approved_by'] = null;
        $data['keterangan_approval'] = null;

        // Pastikan tanggal_pengajuan selalu ada
        $data['tanggal_pengajuan'] = $data['tanggal_pengajuan'] ?? now()->format('Y-m-d');

        return $data;
    }

    // Opsional: Redirect setelah create
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    // Opsional: Notifikasi setelah create
    protected function getCreatedNotificationTitle(): ?string
     {
        return 'Pengajuan Pinjaman berhasil dibuat';
    }
}