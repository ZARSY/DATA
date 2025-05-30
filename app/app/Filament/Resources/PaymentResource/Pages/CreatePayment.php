<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth; // Pastikan Auth diimport
// use App\Models\Loan; // Import jika Anda perlu query Loan di sini

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // user_id diharapkan sudah terisi dari logika form (Select loan_id -> afterStateUpdated -> Set user_id)
        // Jika user_id masih kosong di sini, berarti ada masalah di logika form atau field hidden tidak ter-dehydrate.
        if (empty($data['user_id']) && !empty($data['loan_id'])) {
            // Sebagai fallback, coba ambil lagi dari loan_id jika user_id kosong
            $loan = \App\Models\Loan::find($data['loan_id']); // Gunakan FQCN jika App\Models\Loan tidak di-import
            if ($loan) {
                $data['user_id'] = $loan->user_id;
            }
        }

        // Pastikan user_id tidak null sebelum insert, jika masih null, ada masalah fundamental
        if (empty($data['user_id'])) {
            // Anda bisa throw exception atau log error di sini untuk investigasi lebih lanjut
            // throw new \InvalidArgumentException("User ID untuk angsuran tidak boleh kosong dan tidak dapat ditentukan dari Pinjaman.");
        }


        $data['processed_by'] = Auth::id();
        $data['status_pembayaran'] = $data['status_pembayaran'] ?? 'dikonfirmasi';

        return $data;
    }
}