<?php

namespace App\Filament\Widgets;

use App\Models\Loan;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class LoanInterestRevenueWidget extends BaseWidget
{
    protected static ?int $sort = 3; // Urutan di dashboard, setelah SavingsOverviewWidget

    // Hanya Manajer Keuangan dan Admin yang bisa melihat ini
    public static function canView(): bool
    {
        $user = auth()->user();
        return $user->can('view_financial_reports'); // Minimal bisa lihat semua pinjaman
    }

    protected function getStats(): array
    {
        // Hitung total potensi pendapatan bunga dari pinjaman yang statusnya 'disetujui' atau 'berjalan'
        // Rumus: jumlah_pinjaman * (bunga_persen_per_bulan / 100) * jangka_waktu_bulan
        // Ini adalah estimasi total bunga yang akan didapat jika semua pinjaman lunas tepat waktu.
        // Bunga yang sudah dibayar akan lebih akurat jika dihitung dari tabel payments (memerlukan pemisahan porsi bunga di angsuran).

        $potensiTotalBunga = Loan::whereIn('status', ['disetujui', 'berjalan'])
            // Pastikan bunga_persen_per_bulan tidak null untuk perhitungan
            ->whereNotNull('bunga_persen_per_bulan')
            ->select(DB::raw('SUM(jumlah_pinjaman * (bunga_persen_per_bulan / 100) * jangka_waktu_bulan) as total_bunga_estimasi'))
            ->value('total_bunga_estimasi'); // Langsung ambil nilainya

        // Contoh jika ingin menampilkan jumlah pinjaman aktif
        $totalPinjamanAktif = Loan::whereIn('status', ['disetujui', 'berjalan'])->sum('jumlah_pinjaman');

        return [
            Stat::make('Potensi Total Pendapatan Bunga', 'Rp ' . number_format($potensiTotalBunga ?? 0, 0, ',', '.'))
                ->description('Estimasi total bunga dari pinjaman aktif & disetujui')
                ->descriptionIcon('heroicon-m-scale')
                ->color('primary'),

            Stat::make('Total Pinjaman Aktif (Outstanding)', 'Rp ' . number_format($totalPinjamanAktif ?? 0, 0, ',', '.'))
                ->description('Total pokok pinjaman yang masih berjalan/disetujui')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),
        ];
    }
}