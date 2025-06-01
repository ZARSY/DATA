<?php

namespace App\Filament\Widgets;

use App\Models\Saving; // Pastikan model Saving diimpor
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB; // Untuk SUM

class SavingsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 2; // Urutan di dashboard, setelah FinancialFlowChart

    // Kontrol siapa yang bisa melihat widget ini
    public static function canView(): bool
    {
        $user = auth()->user();
        // Admin, Manajer Keuangan, dan Teller bisa melihat ringkasan simpanan
        return $user->hasAnyRole(['Admin', 'Manajer Keuangan', 'Teller']) &&
               $user->can('view_any_savings');
    }

    protected function getStats(): array
    {
        // Hitung total simpanan berdasarkan jenis dan status 'dikonfirmasi'
        $totalPokok = Saving::where('jenis_simpanan', 'pokok')
                            ->where('status', 'dikonfirmasi')
                            ->sum('jumlah');

        $totalWajib = Saving::where('jenis_simpanan', 'wajib')
                            ->where('status', 'dikonfirmasi')
                            ->sum('jumlah');

        $totalSukarela = Saving::where('jenis_simpanan', 'sukarela')
                               ->where('status', 'dikonfirmasi')
                               ->sum('jumlah');

        $totalSemuaSimpanan = $totalPokok + $totalWajib + $totalSukarela;

        return [
            Stat::make('Total Simpanan Pokok', 'Rp ' . number_format($totalPokok, 0, ',', '.'))
                ->description('Total simpanan pokok terkonfirmasi')
                ->descriptionIcon('heroicon-m-arrow-trending-up') // Ganti ikon jika perlu
                ->color('success')
                // Jika ingin ada link ke SavingResource yang difilter (contoh)
                ->url(route('filament.admin.resources.savings.index', ['tableFilters[jenis_simpanan][value]' => 'pokok'])),


            Stat::make('Total Simpanan Wajib', 'Rp ' . number_format($totalWajib, 0, ',', '.'))
                ->description('Total simpanan wajib terkonfirmasi')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('info')
                ->url(route('filament.admin.resources.savings.index', ['tableFilters[jenis_simpanan][value]' => 'wajib'])),

            Stat::make('Total Simpanan Sukarela', 'Rp ' . number_format($totalSukarela, 0, ',', '.'))
                ->description('Total simpanan sukarela terkonfirmasi')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('warning')
                ->url(route('filament.admin.resources.savings.index', ['tableFilters[jenis_simpanan][value]' => 'sukarela'])),

            Stat::make('Total Semua Simpanan', 'Rp ' . number_format($totalSemuaSimpanan, 0, ',', '.'))
                ->description('Total semua jenis simpanan terkonfirmasi')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary')
                ->url(route('filament.admin.resources.savings.index')), // Link ke semua simpanan
        ];
    }
}