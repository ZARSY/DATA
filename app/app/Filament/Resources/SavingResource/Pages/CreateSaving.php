<?php

namespace App\Filament\Resources\SavingResource\Pages;

use App\Filament\Resources\SavingResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth; // <-- IMPORT

class CreateSaving extends CreateRecord
{
    protected static string $resource = SavingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $loggedInUser = Auth::user();
        // Jika form status tidak visible/disabled (misal oleh Anggota jika mereka bisa input)
        // atau jika yang input adalah Teller tanpa izin konfirmasi langsung
        if (empty($data['status']) || ($loggedInUser && !$loggedInUser->can('confirm_savings'))) {
            $data['status'] = 'pending_approval';
        }
        // Jika Admin/Teller dengan izin konfirmasi, nilai 'status' dari form akan digunakan

        // Jika Anggota yang membuat (dan field user_id disabled), set user_id di sini
        if ($loggedInUser && $loggedInUser->hasRole('Anggota') && empty($data['user_id'])) {
            $data['user_id'] = $loggedInUser->id;
        }
        return $data;
    }
}