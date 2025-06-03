<?php

namespace App\Filament\Widgets;

use App\Models\Saving;
use App\Models\Loan;
use App\Models\User;
use App\Filament\Resources\SavingResource;
use App\Filament\Resources\LoanResource;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class MemberFinancialSummaryWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Auth::check() && Auth::user()->hasRole('Anggota');
    }

    protected function getStats(): array
    {
        /** @var User $loggedInUser */
        $loggedInUser = Auth::user();

        if (!$loggedInUser) {
            return [];
        }

        // 1. Hitung Total Saldo Simpanan Anggota
        $totalSaldoSimpanan = Saving::where('user_id', $loggedInUser->id)
                                    ->where('status', 'dikonfirmasi')
                                    ->sum('jumlah');

        // 2. Hitung Total Pinjaman Aktif Anggota (Jumlah Pokok)
        $totalPinjamanAktif = Loan::where('user_id', $loggedInUser->id)
                                   ->whereIn('status', ['disetujui', 'berjalan'])
                                   ->sum('jumlah_pinjaman');

        // 3. Hitung Total Sisa Estimasi Tagihan Anggota (Pokok + Estimasi Bunga)
        $pinjamanAnggota = Loan::where('user_id', $loggedInUser->id)
                                ->whereIn('status', ['disetujui', 'berjalan'])
                                ->get();

        $totalSisaEstimasiTagihan = 0;
        foreach ($pinjamanAnggota as $pinjaman) {
            // Memanggil accessor sisa_estimasi_tagihan dari model Loan
            // Pastikan accessor ini ada dan namanya benar (getSisaEstimasiTagihanAttribute)
            // dan sudah menghitung (Pokok + Total Estimasi Bunga) - Total Pembayaran
            $totalSisaEstimasiTagihan += $pinjaman->sisa_estimasi_tagihan;
        }

        return [
            Stat::make('Total Saldo Simpanan Anda', 'Rp ' . number_format($totalSaldoSimpanan, 0, ',', '.'))
                ->description('Semua jenis simpanan yang telah dikonfirmasi')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->url(SavingResource::getUrl('index')),

            Stat::make('Total Pinjaman Aktif Anda (Pokok)', 'Rp ' . number_format($totalPinjamanAktif, 0, ',', '.'))
                ->description('Jumlah pokok pinjaman yang disetujui/berjalan')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('warning')
                ->url(LoanResource::getUrl('index')),

            Stat::make('Total Sisa Tagihan Anda (Estimasi)', 'Rp ' . number_format($totalSisaEstimasiTagihan, 0, ',', '.')) // <-- LABEL DAN NILAI DIPERBARUI
                ->description('Estimasi sisa tagihan pinjaman (termasuk bunga)') // <-- DESKRIPSI DIPERBARUI
                ->descriptionIcon('heroicon-m-calculator')
                ->color('danger')
                ->url(LoanResource::getUrl('index')),
        ];
    }
}