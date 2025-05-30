<?php

namespace App\Providers;

// Import Model dan Policy Anda
use App\Models\User;
use App\Policies\UserPolicy;
use App\Models\Saving;
use App\Policies\SavingPolicy;
use App\Models\Loan;
use App\Policies\LoanPolicy;
use App\Models\Payment;
use App\Policies\PaymentPolicy;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider; // PENTING: Tetap extend AuthServiceProvider
// use Illuminate\Support\Facades\Gate; // Uncomment jika Anda menggunakan Gate

class PolicyServiceProvider extends ServiceProvider // Nama class sesuai yang dibuat
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Saving::class => SavingPolicy::class,
        Loan::class => LoanPolicy::class,
        Payment::class => PaymentPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Panggilan $this->registerPolicies(); biasanya tidak eksplisit diperlukan di sini
        // karena base AuthServiceProvider akan menanganinya jika array $policies diisi.
        // Namun, tidak masalah jika ditambahkan untuk kepastian.
        // $this->registerPolicies();

        // Contoh jika ingin memberi akses penuh ke Admin via Gate::before
        // Gate::before(function ($user, $ability) {
        //     return $user->hasRole('Admin') ? true : null;
        // });
    }
}