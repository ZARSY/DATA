<?php

namespace App\Notifications;

use App\Models\Loan;
use App\Models\User;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Carbon\Carbon; // Import Carbon

class LoanDueDateReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public Loan $loan;
    public Carbon $dueDate;

    public function __construct(Loan $loan, Carbon $dueDate)
    {
        $this->loan = $loan;
        $this->dueDate = $dueDate;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Anggota mungkin tidak memiliki akses langsung ke panel admin untuk edit loan,
        // jadi URL bisa ke halaman profil anggota atau halaman informasi pinjaman khusus anggota jika ada.
        // Untuk sekarang, kita arahkan ke view loan di panel admin, dengan asumsi mereka bisa lihat.
        $loanUrl = route('filament.admin.resources.loans.view', ['record' => $this->loan]);

        return (new MailMessage)
                    ->subject('Pengingat Jatuh Tempo Pembayaran Pinjaman')
                    ->greeting('Halo ' . $notifiable->name . ',')
                    ->line("Angsuran pinjaman Anda (ID: {$this->loan->id}) sejumlah Rp " . number_format($this->loan->jumlah_pinjaman, 0, ',', '.') . " akan jatuh tempo pada tanggal: " . $this->dueDate->isoFormat('D MMMM YYYY') . ".")
                    ->line('Mohon segera lakukan pembayaran untuk menghindari denda (jika ada).')
                    ->action('Lihat Detail Pinjaman', $loanUrl)
                    ->line('Terima kasih.');
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('🔴 SEGERA JATUH TEMPO!')
            ->icon('heroicon-o-clock')
            ->warning() // Warna notifikasi
            ->body("Angsuran Pinjaman ID {$this->loan->id} akan jatuh tempo pada {$this->dueDate->isoFormat('D MMMM YYYY')}.")
            ->actions([
                FilamentNotification\Actions\Action::make('lihat_detail')
                    ->button()->color('primary')
                    ->url(route('filament.admin.resources.loans.view', ['record' => $this->loan]), shouldOpenInNewTab: true),
            ])
            ->getDatabaseMessage();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'loan_id' => $this->loan->id,
            'due_date' => $this->dueDate->toIso8601String(),
        ];
    }
}