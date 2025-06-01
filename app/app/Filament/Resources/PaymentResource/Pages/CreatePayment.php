<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $loggedInUser = Auth::user();

        // Fallback: Set user_id dari loan jika belum diisi oleh form (afterStateUpdated)
        if (empty($data['user_id']) && !empty($data['loan_id'])) {
            $loan = \App\Models\Loan::find($data['loan_id']);
            if ($loan) {
                $data['user_id'] = $loan->user_id;
            }
        }

        // Validasi terakhir: Pastikan user_id tidak kosong
        if (empty($data['user_id'])) {
            // throw new \InvalidArgumentException("User ID tidak boleh kosong.");
            // Atau log error sesuai kebutuhan
        }

        // Logika status pembayaran
        if (empty($data['status'])) {
            if (($data['metode_pembayaran'] ?? '') === 'transfer_bank') {
                $data['status'] = 'pending_approval';
            } else {
                $data['status'] = $loggedInUser->can('confirm_payments') ? 'dikonfirmasi' : 'pending_approval';
            }
        }

        // Set processed_by
        $data['processed_by'] = $loggedInUser->id;

        return $data;
    }
}
