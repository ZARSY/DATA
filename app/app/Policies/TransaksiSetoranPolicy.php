<?php
namespace App\Policies;
use App\Models\User;
class TransaksiSetoranPolicy
{
    // Siapa yang boleh melihat menu/resource ini sama sekali
    // public function viewAny(User $user): bool {
    //     return $user->hasAnyRole(['Admin', 'Teller']) && $user->can('create_savings');
    // }
    // Siapa yang boleh membuat (mengakses form create)
    public function create(User $user): bool {
        return $user->hasAnyRole(['Admin', 'Teller']) && $user->can('create_savings');
    }
}