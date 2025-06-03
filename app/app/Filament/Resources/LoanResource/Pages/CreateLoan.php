<?php

namespace App\Filament\Resources\LoanResource\Pages;

use App\Filament\Resources\LoanResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use App\Models\Loan; // Untuk type hinting record
use App\Models\User; // Untuk type hinting applicant
use App\Notifications\LoanApprovalNeeded;
use App\Helpers\NotificationRecipients; // <-- PERBAIKAN DI SINI
use Illuminate\Support\Facades\Notification as NotificationFacade;

class CreateLoan extends CreateRecord
{
    protected static string $resource = LoanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $loggedInUser = Auth::user();
        if ($loggedInUser && $loggedInUser->hasRole('Anggota')) {
            $data['user_id'] = $loggedInUser->id;
        }
        $data['status'] = 'diajukan';
        $data['tanggal_persetujuan'] = null;
        $data['bunga_persen_per_bulan'] = $data['bunga_persen_per_bulan'] ?? 0.00;
        $data['approved_by'] = null;
        $data['keterangan_approval'] = null;
        $data['tanggal_pengajuan'] = $data['tanggal_pengajuan'] ?? now()->format('Y-m-d');
        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var \App\Models\Loan $loan */
        $loan = $this->record;
        /** @var \App\Models\User $applicant */
        $applicant = $loan->user;
    
        // Kirim notifikasi HANYA jika statusnya memang 'diajukan'
        if ($loan->status === 'diajukan' && $applicant) {
            $approvers = NotificationRecipients::getLoanApprovers(); // Panggil helper yang benar
            if ($approvers->isNotEmpty()) {
                NotificationFacade::send($approvers, new LoanApprovalNeeded($loan, $applicant));
            } else {
                // Opsional: Log jika tidak ada approver yang ditemukan
                Log::warning('Tidak ada approver yang ditemukan untuk notifikasi persetujuan pinjaman ID: ' . $loan->id);
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
       return 'Pengajuan Pinjaman berhasil dibuat dan menunggu persetujuan.';
    }
}