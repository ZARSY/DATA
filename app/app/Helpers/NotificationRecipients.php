<?php

namespace App\Helpers;

use App\Models\User;

class NotificationRecipients
{
    public static function getLoanApprovers()
    {
        return User::role(['Manajer Keuangan', 'Admin']) // Peran yang berhak
                ->whereHas('permissions', function ($query) {
                    $query->where('name', 'approve_loans'); // Izin yang dibutuhkan
                })
                ->get();
    }

    public static function getSavingConfirmers()
    {
    return User::role(['Teller', 'Admin']) // Peran yang berhak
               ->whereHas('permissions', function ($query) {
                   $query->where('name', 'confirm_savings'); // Izin yang dibutuhkan
               })
               ->get();
    }

    public static function getPaymentConfirmers()
    {
    return User::role(['Teller', 'Admin']) // Peran yang berhak
               ->whereHas('permissions', function ($query) {
                   $query->where('name', 'confirm_payments'); // Izin yang dibutuhkan
               })
               ->get();
    }
}