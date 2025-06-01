<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Saving;
use App\Models\Loan;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB; // Pastikan DB facade diimpor

class FinancialFlowChart extends ChartWidget
{
    protected static ?string $heading = 'Grafik Arus Keuangan (12 Bulan Terakhir)';

    protected static ?string $pollingInterval = null; // Nonaktifkan polling otomatis, atau set interval (misal '30s')

    protected int | string | array $columnSpan = 'full'; // Agar widget mengambil lebar penuh

    protected static ?int $sort = 1; // Urutan widget di dashboard (opsional)

    /**
     * Mengontrol siapa yang bisa melihat widget ini.
     * Hanya Admin dan Manajer Keuangan.
     * Pastikan permission ini sesuai dengan yang ada di RolesAndPermissionsSeeder Anda.
     */
    public static function canView(): bool
    {
        $user = auth()->user();
        // Hanya yang punya permission 'view_financial_dashboard_widget' yang bisa lihat
        // Permission ini diberikan ke Admin dan Manajer Keuangan di seeder
        return $user->can('view_financial_dashboard_widget');
    }

    protected function getData(): array
    {
        $startDate = Carbon::now()->subMonths(11)->startOfMonth(); // 12 bulan data, termasuk bulan ini
        $endDate = Carbon::now()->endOfMonth();

        $labels = [];
        $uangMasukPerBulan = [];
        $uangKeluarPerBulan = [];
        $arusBersihPerBulan = [];

        // Inisialisasi array data dengan 0 untuk setiap bulan dalam rentang
        $currentMonthIterator = $startDate->copy();
        while ($currentMonthIterator <= $endDate) {
            $monthYearKey = $currentMonthIterator->format('Y-m');
            $labels[] = $currentMonthIterator->format('M Y'); // Label untuk sumbu X (Contoh: Jan 2023)
            $uangMasukPerBulan[$monthYearKey] = 0;
            $uangKeluarPerBulan[$monthYearKey] = 0;
            $arusBersihPerBulan[$monthYearKey] = 0;
            $currentMonthIterator->addMonth();
        }

        // 1. Kalkulasi Uang Masuk dari Simpanan yang Dikonfirmasi
        $simpananMasuk = Saving::query()
            ->whereBetween('tanggal_transaksi', [$startDate, $endDate])
            ->where('status', 'dikonfirmasi') // Hanya simpanan yang sudah dikonfirmasi
            ->select(
                DB::raw("SUM(jumlah) as total_jumlah"),
                DB::raw("DATE_FORMAT(tanggal_transaksi, '%Y-%m') as bulan_tahun")
            )
            ->groupBy('bulan_tahun')
            ->get();

        foreach ($simpananMasuk as $simpanan) {
            if (isset($uangMasukPerBulan[$simpanan->bulan_tahun])) {
                $uangMasukPerBulan[$simpanan->bulan_tahun] += $simpanan->total_jumlah;
            }
        }

        // 2. Kalkulasi Uang Masuk dari Angsuran yang Dikonfirmasi
        $angsuranMasuk = Payment::query()
            ->whereBetween('tanggal_pembayaran', [$startDate, $endDate])
            ->where('status', 'dikonfirmasi') // Hanya angsuran yang sudah dikonfirmasi
            ->select(
                DB::raw("SUM(jumlah_pembayaran) as total_jumlah"),
                DB::raw("DATE_FORMAT(tanggal_pembayaran, '%Y-%m') as bulan_tahun")
            )
            ->groupBy('bulan_tahun')
            ->get();

        foreach ($angsuranMasuk as $angsuran) {
            if (isset($uangMasukPerBulan[$angsuran->bulan_tahun])) {
                $uangMasukPerBulan[$angsuran->bulan_tahun] += $angsuran->total_jumlah;
            }
        }

        // 3. Kalkulasi Uang Keluar dari Pinjaman yang Disetujui/Berjalan
        // Kita gunakan tanggal persetujuan sebagai proxy tanggal uang keluar
        $pinjamanKeluar = Loan::query()
            ->whereNotNull('tanggal_persetujuan')
            ->whereBetween('tanggal_persetujuan', [$startDate, $endDate])
            ->whereIn('status', ['disetujui', 'berjalan']) // Pinjaman yang benar-benar cair
            ->select(
                DB::raw("SUM(jumlah_pinjaman) as total_jumlah"),
                DB::raw("DATE_FORMAT(tanggal_persetujuan, '%Y-%m') as bulan_tahun")
            )
            ->groupBy('bulan_tahun')
            ->get();

        foreach ($pinjamanKeluar as $pinjaman) {
            if (isset($uangKeluarPerBulan[$pinjaman->bulan_tahun])) {
                $uangKeluarPerBulan[$pinjaman->bulan_tahun] += $pinjaman->total_jumlah;
            }
        }

        // 4. Kalkulasi Arus Bersih per Bulan
        foreach ($uangMasukPerBulan as $bulan_tahun => $masuk) {
            $keluar = $uangKeluarPerBulan[$bulan_tahun] ?? 0;
            $arusBersihPerBulan[$bulan_tahun] = $masuk - $keluar;
        }

        // Memastikan data yang dikembalikan ke chart memiliki urutan yang sama dengan labels
        $finalUangMasuk = [];
        $finalUangKeluar = [];
        $finalArusBersih = [];
        $tempCurrentMonth = $startDate->copy();
        while ($tempCurrentMonth <= $endDate) {
            $monthYearKey = $tempCurrentMonth->format('Y-m');
            $finalUangMasuk[] = $uangMasukPerBulan[$monthYearKey] ?? 0;
            $finalUangKeluar[] = $uangKeluarPerBulan[$monthYearKey] ?? 0;
            $finalArusBersih[] = $arusBersihPerBulan[$monthYearKey] ?? 0;
            $tempCurrentMonth->addMonth();
        }


        return [
            'datasets' => [
                [
                    'label' => 'Uang Masuk',
                    'data' => $finalUangMasuk,
                    'borderColor' => 'rgb(75, 192, 192)',
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'tension' => 0.1,
                ],
                [
                    'label' => 'Uang Keluar',
                    'data' => $finalUangKeluar,
                    'borderColor' => 'rgb(255, 99, 132)',
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'tension' => 0.1,
                ],
                [
                    'label' => 'Arus Bersih (Aset Flow)',
                    'data' => $finalArusBersih,
                    'borderColor' => 'rgb(54, 162, 235)',
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    'tension' => 0.1,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line'; // Jenis grafik: line, bar, pie, doughnut, radar, polarArea
    }
}