<?php

namespace App\Notifications;

use App\Models\Loan;
use App\Models\User;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoanApprovalNeeded extends Notification implements ShouldQueue
{
    use Queueable;

    public Loan $loan;
    public User $applicant;

    public function __construct(Loan $loan, User $applicant)
    {
        $this->loan = $loan;
        $this->applicant = $applicant;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail']; // Kirim ke database (Filament) dan email
    }

    public function toMail(object $notifiable): MailMessage
    {
        $loanUrl = route('filament.admin.resources.loans.edit', ['record' => $this->loan]);

        return (new MailMessage)
                    ->subject('Persetujuan Pinjaman Diperlukan')
                    ->greeting('Halo ' . $notifiable->name . ',')
                    ->line("Pengajuan pinjaman baru dari {$this->applicant->name} (ID Pinj: {$this->loan->id}) sejumlah Rp " . number_format($this->loan->jumlah_pinjaman, 0, ',', '.') . " memerlukan persetujuan Anda.")
                    ->action('Lihat Detail Pinjaman', $loanUrl)
                    ->line('Terima kasih.');
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Persetujuan Pinjaman Baru')
            ->icon('heroicon-o-credit-card')
            ->body("Pinjaman dari {$this->applicant->name} (Rp " . number_format($this->loan->jumlah_pinjaman, 0, ',', '.') . ") menunggu persetujuan.")
            ->actions([
                FilamentNotification\Actions\Action::make('lihat_pinjaman')
                    ->button()
                    ->url(route('filament.admin.resources.loans.edit', ['record' => $this->loan]), shouldOpenInNewTab: true),
            ])
            ->getDatabaseMessage(); // Ini akan menghasilkan array yang sesuai untuk notifikasi database Filament
    }

    // Opsional: toArray untuk channel lain atau broadcasting
    public function toArray(object $notifiable): array
    {
        return [
            'loan_id' => $this->loan->id,
            'applicant_name' => $this->applicant->name,
            'amount' => $this->loan->jumlah_pinjaman,
        ];
    }
}