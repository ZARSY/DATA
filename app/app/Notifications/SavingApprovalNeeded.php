<?php

namespace App\Notifications;

use App\Models\Saving;
use App\Models\User;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SavingApprovalNeeded extends Notification implements ShouldQueue
{
    use Queueable;

    public Saving $saving;
    public User $member;

    public function __construct(Saving $saving, User $member)
    {
        $this->saving = $saving;
        $this->member = $member;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $savingUrl = route('filament.admin.resources.savings.edit', ['record' => $this->saving]);

        return (new MailMessage)
                    ->subject('Konfirmasi Simpanan Diperlukan')
                    ->greeting('Halo ' . $notifiable->name . ',')
                    ->line("Simpanan baru dari {$this->member->name} (ID Simpanan: {$this->saving->id}) sejumlah Rp " . number_format($this->saving->jumlah, 0, ',', '.') . " ({$this->saving->jenis_simpanan}) memerlukan konfirmasi Anda.")
                    ->action('Lihat Detail Simpanan', $savingUrl)
                    ->line('Terima kasih.');
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Konfirmasi Simpanan Baru')
            ->icon('heroicon-o-banknotes') // Ganti ikon jika perlu
            ->body("Simpanan dari {$this->member->name} (Rp " . number_format($this->saving->jumlah, 0, ',', '.') . " - {$this->saving->jenis_simpanan}) menunggu konfirmasi.")
            ->actions([
                FilamentNotification\Actions\Action::make('lihat_simpanan')
                    ->button()
                    ->url(route('filament.admin.resources.savings.edit', ['record' => $this->saving]), shouldOpenInNewTab: true),
            ])
            ->getDatabaseMessage();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'saving_id' => $this->saving->id,
            'member_name' => $this->member->name,
            'amount' => $this->saving->jumlah,
            'saving_type' => $this->saving->jenis_simpanan,
        ];
    }
}