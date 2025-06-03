<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Models\User;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentApprovalNeeded extends Notification implements ShouldQueue
{
    use Queueable;

    public Payment $payment;
    public User $member; // Anggota pemilik pinjaman

    public function __construct(Payment $payment, User $member)
    {
        $this->payment = $payment;
        $this->member = $member;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $paymentUrl = route('filament.admin.resources.payments.edit', ['record' => $this->payment]);

        return (new MailMessage)
                    ->subject('Konfirmasi Angsuran Diperlukan')
                    ->greeting('Halo ' . $notifiable->name . ',')
                    ->line("Pembayaran angsuran dari {$this->member->name} (ID Pinj: {$this->payment->loan_id}, ID Angs: {$this->payment->id}) sejumlah Rp " . number_format($this->payment->jumlah_pembayaran, 0, ',', '.') . " memerlukan konfirmasi Anda.")
                    ->action('Lihat Detail Angsuran', $paymentUrl)
                    ->line('Terima kasih.');
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Konfirmasi Angsuran Baru')
            ->icon('heroicon-o-currency-dollar')
            ->body("Angsuran dari {$this->member->name} untuk Pinj. ID {$this->payment->loan_id} (Rp " . number_format($this->payment->jumlah_pembayaran, 0, ',', '.') . ") menunggu konfirmasi.")
            ->actions([
                FilamentNotification\Actions\Action::make('lihat_angsuran')
                    ->button()
                    ->url(route('filament.admin.resources.payments.edit', ['record' => $this->payment]), shouldOpenInNewTab: true),
            ])
            ->getDatabaseMessage();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'payment_id' => $this->payment->id,
            'loan_id' => this->payment->loan_id,
            'member_name' => $this->member->name,
            'amount' => $this->payment->jumlah_pembayaran,
        ];
    }
}