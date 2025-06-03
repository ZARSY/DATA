<?php

namespace App\Filament\Resources\SavingResource\Pages;

use App\Filament\Resources\SavingResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use App\Models\Saving;
use App\Models\User;
use App\Notifications\SavingApprovalNeeded;
use App\Helpers\NotificationRecipients;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class CreateSaving extends CreateRecord
{
    protected static string $resource = SavingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $loggedInUser = Auth::user();

        // Jika yang membuat adalah Anggota:
        if ($loggedInUser && $loggedInUser->hasRole('Anggota')) {
            $data['user_id'] = $loggedInUser->id; // Pastikan user_id adalah dirinya
            $data['status'] = 'pending_approval'; // Simpanan dari Anggota selalu pending approval
            // Jenis simpanan ($data['jenis_simpanan']) akan diambil dari apa yang dipilih Anggota di form.
            // Anda bisa menambahkan validasi di sini jika Anggota hanya boleh memilih jenis tertentu.
            // Misalnya:
            // if (!in_array($data['jenis_simpanan'], ['sukarela', 'wajib_khusus_anggota'])) {
            //     throw \Illuminate\Validation\ValidationException::withMessages([
            //         'jenis_simpanan' => 'Anda hanya dapat memilih jenis simpanan tertentu.',
            //     ]);
            // }
        } else if ($loggedInUser && ($loggedInUser->hasRole('Admin') || $loggedInUser->hasRole('Teller'))) {
            // Jika Admin/Teller yang input:
            // user_id harus sudah dipilih dari form.
            // status bisa diatur dari form jika mereka punya izin, atau default ke pending_approval.
            if (empty($data['status'])) {
                $data['status'] = $loggedInUser->can('confirm_savings') ? 'dikonfirmasi' : 'pending_approval';
            } elseif ($data['status'] === 'dikonfirmasi' && !$loggedInUser->can('confirm_savings')) {
                $data['status'] = 'pending_approval';
            }
        }
        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Saving $saving */
        $saving = $this->record;
        /** @var User $member */
        $member = $saving->user;

        if (($saving->status === 'pending_approval' || $saving->status === 'pending') && $member) {
            $confirmers = NotificationRecipients::getSavingConfirmers();
            if ($confirmers->isNotEmpty()) {
                NotificationFacade::send($confirmers, new SavingApprovalNeeded($saving, $member));
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        $status = $this->record->status;
        if ($status === 'pending_approval' || $status === 'pending') {
            return 'Simpanan berhasil diajukan dan menunggu konfirmasi.';
        } elseif ($status === 'dikonfirmasi') {
            return 'Simpanan berhasil dibuat dan dikonfirmasi.';
        }
        return 'Simpanan berhasil dibuat.';
    }
}