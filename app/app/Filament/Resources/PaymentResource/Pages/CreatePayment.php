<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use App\Models\Payment; // Untuk type hinting record
use App\Models\Loan;
use App\Models\User;    // Untuk type hinting member
use App\Notifications\PaymentApprovalNeeded;
use App\Helpers\NotificationRecipients;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $loggedInUser = Auth::user();

        if (empty($data['user_id']) && !empty($data['loan_id'])) {
            $loan = Loan::find($data['loan_id']);
            if ($loan) {
                $data['user_id'] = $loan->user_id;
            }
        }

        if (empty($data['status'])) { // Jika status tidak diisi dari form
            // Jika metode transfer, default pending. Jika tunai, bisa langsung dikonfirmasi oleh Teller.
            if (isset($data['metode_pembayaran']) && $data['metode_pembayaran'] === 'transfer_bank') {
                 $data['status'] = 'pending_approval';
            } else { // Tunai atau auto debet
                 $data['status'] = ($loggedInUser && $loggedInUser->can('confirm_payments')) ? 'dikonfirmasi' : 'pending_approval';
            }
        } else {
             if ($data['status'] === 'dikonfirmasi' && $loggedInUser && $loggedInUser->hasRole('Teller') && !$loggedInUser->can('confirm_payments')) {
                $data['status'] = 'pending_approval';
            }
        }

        $data['processed_by'] = $loggedInUser->id;
        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Payment $payment */
        $payment = $this->record;
        /** @var User $member */
        $member = $payment->member; // Menggunakan relasi member() di model Payment

        if ($payment->status === 'pending_approval' && $member) {
            $confirmers = NotificationRecipients::getPaymentConfirmers();
            if ($confirmers->isNotEmpty()) {
                NotificationFacade::send($confirmers, new PaymentApprovalNeeded($payment, $member));
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
        if ($status === 'pending_approval') {
            return 'Angsuran berhasil diajukan dan menunggu konfirmasi.';
        } elseif ($status === 'dikonfirmasi') {
            return 'Angsuran berhasil dicatat dan dikonfirmasi.';
        }
        return 'Angsuran berhasil dicatat.';
    }
}