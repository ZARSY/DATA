<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Loan;
use App\Notifications\LoanDueDateReminder;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Carbon\Carbon;

class SendLoanDueDateReminders extends Command
{
    protected $signature = 'app:send-loan-due-date-reminders {--days=3 : Jumlah hari sebelum jatuh tempo untuk mengirim pengingat}';
    protected $description = 'Mengirim pengingat untuk pinjaman yang mendekati tanggal jatuh tempo berikutnya.';

    public function handle()
    {
        $daysBefore = (int) $this->option('days');
        $this->info("Mencari pinjaman yang jatuh tempo dalam {$daysBefore} hari...");

        // Logika untuk menentukan "jatuh tempo berikutnya" bisa sangat kompleks.
        // Ini adalah contoh yang SANGAT DISEDERHANAKAN dan perlu Anda sesuaikan
        // dengan bagaimana Anda melacak jadwal angsuran.
        // ASUMSI: Kita ingatkan X hari sebelum tanggal akhir pinjaman (bukan per angsuran).
        // Untuk sistem nyata, Anda akan memiliki tabel `loan_schedules` atau `installments`.

        $targetDueDate = Carbon::now()->addDays($daysBefore);

        $loans = Loan::with('user')
            ->whereIn('status', ['disetujui', 'berjalan']) // Hanya pinjaman aktif
            // ->whereDate(DB::raw("DATE_ADD(tanggal_persetujuan, INTERVAL jangka_waktu_bulan MONTH)"), '=', $targetDueDate->toDateString()) // Contoh query jika tanggal_akhir_pinjaman tidak ada sebagai kolom asli
            ->get();

        $remindersSent = 0;
        foreach ($loans as $loan) {
            // Hitung tanggal akhir pinjaman berdasarkan accessor jika tidak ada kolom langsung
            $tanggalAkhirPinjaman = $loan->tanggal_akhir_pinjaman; // Menggunakan accessor

            if ($tanggalAkhirPinjaman) {
                $dueDate = Carbon::parse($tanggalAkhirPinjaman);

                // Cek apakah tanggal akhir adalah target pengingat kita
                if ($dueDate->isSameDay($targetDueDate)) {
                    $member = $loan->user;
                    if ($member) {
                        // Hindari mengirim notifikasi berulang jika sudah pernah dikirim untuk jatuh tempo ini
                        // Anda mungkin perlu mekanisme pelacakan notifikasi terkirim
                        NotificationFacade::send($member, new LoanDueDateReminder($loan, $dueDate));
                        $this->info("Pengingat dikirim ke {$member->name} untuk pinjaman ID {$loan->id} (Jatuh Tempo: {$dueDate->toDateString()})");
                        $remindersSent++;
                    }
                }
            }
        }

        if ($remindersSent > 0) {
            $this->info("{$remindersSent} pengingat jatuh tempo telah dikirim.");
        } else {
            $this->info('Tidak ada pinjaman yang jatuh tempo dalam waktu dekat untuk diingatkan.');
        }
        return Command::SUCCESS;
    }
}