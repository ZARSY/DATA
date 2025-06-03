<?php

namespace App\Filament\Resources\TransaksiSetoranResource\Pages;

use App\Filament\Resources\TransaksiSetoranResource;
use App\Filament\Resources\SavingResource; // <-- IMPORT SavingResource
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Saving;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use App\Notifications\SavingApprovalNeeded;
use App\Helpers\NotificationRecipients;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Filament\Support\Exceptions\Halt; // <-- IMPORT Halt exception

class CreateTransaksiSetoran extends CreateRecord
{
    protected static string $resource = TransaksiSetoranResource::class;

    // ... (method create() Anda yang sudah ada)
    public function create(bool $another = false): void
    {
        $this->authorizeAccess();

        try {
            $data = $this->form->getState();
            $memberId = $data['user_id'];
            /** @var User $member */
            $member = User::find($memberId);
            $loggedInUser = Auth::user();
            $savingsToCreate = [];
            $createdCount = 0; // Inisialisasi createdCount

            if (!$member) {
                Notification::make()->title('Error')->body('Anggota tidak ditemukan.')->danger()->send();
                return;
            }

            $simpananTypes = [
                'pokok' => ['jumlah_field' => 'jumlah_pokok', 'tanggal_field' => 'tanggal_transaksi_pokok', 'bukti_field' => 'bukti_transfer_pokok', 'keterangan_field' => 'keterangan_pokok'],
                'wajib' => ['jumlah_field' => 'jumlah_wajib', 'tanggal_field' => 'tanggal_transaksi_wajib', 'bukti_field' => 'bukti_transfer_wajib', 'keterangan_field' => 'keterangan_wajib'],
                'sukarela' => ['jumlah_field' => 'jumlah_sukarela', 'tanggal_field' => 'tanggal_transaksi_sukarela', 'bukti_field' => 'bukti_transfer_sukarela', 'keterangan_field' => 'keterangan_sukarela'],
            ];

            foreach ($simpananTypes as $jenis => $fields) {
                if (!empty($data[$fields['jumlah_field']]) && $data[$fields['jumlah_field']] > 0) {
                    $statusSimpanan = 'pending_approval';
                    if ($loggedInUser->can('confirm_savings')) {
                        $statusSimpanan = 'dikonfirmasi';
                    }
                    $savingsToCreate[] = [
                        'user_id' => $memberId,
                        'jenis_simpanan' => $jenis,
                        'jumlah' => $data[$fields['jumlah_field']],
                        'tanggal_transaksi' => $data[$fields['tanggal_field']] ?? now()->format('Y-m-d'),
                        'bukti_transfer' => $data[$fields['bukti_field']] ?? null,
                        'keterangan' => $data[$fields['keterangan_field']] ?? null,
                        'status' => $statusSimpanan,
                        'processed_by' => $loggedInUser->id, // Tambahkan processed_by
                    ];
                }
            }

            if (empty($savingsToCreate)) {
                Notification::make()
                    ->title('Tidak Ada Data Disimpan')
                    ->body('Mohon isi setidaknya satu jenis simpanan.')
                    ->warning()
                    ->send();
                return;
            }

            DB::transaction(function () use ($savingsToCreate, $member, &$createdCount) { // Pass $createdCount by reference
                foreach ($savingsToCreate as $savingData) {
                    $saving = Saving::create($savingData);
                    $createdCount++; // Increment counter
                    if ($saving->status === 'pending_approval' && $member) {
                        // Pastikan NotificationRecipients dan SavingApprovalNeeded ada dan benar
                        if (class_exists(NotificationRecipients::class) && class_exists(SavingApprovalNeeded::class)) {
                            $confirmers = NotificationRecipients::getSavingConfirmers();
                            if ($confirmers && $confirmers->isNotEmpty()) {
                                NotificationFacade::send($confirmers, new SavingApprovalNeeded($saving, $member));
                            }
                        }
                    }
                }
            });

        } catch (Halt $exception) {
            return;
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Error Penyimpanan')
                ->body('Terjadi kesalahan saat menyimpan data simpanan: ' . $exception->getMessage())
                ->danger()
                ->send();
            return;
        }

        Notification::make()
            ->title('Setoran Simpanan Berhasil')
            ->body("{$createdCount} jenis simpanan berhasil dicatat untuk {$member->name}.")
            ->success()
            ->send();

        if ($another) {
            $this->form->fill();
            return;
        }
        // Redirect setelah semua proses selesai
        $this->redirect($this->getRedirectUrl());
    }


    // PASTIKAN METHOD INI SEPERTI DI BAWAH:
    protected function getRedirectUrl(): string
    {
        // Arahkan ke halaman daftar Simpanan (SavingResource) setelah berhasil
        return SavingResource::getUrl('index');
    }

    // Hapus atau komentari method ini jika tidak ingin notifikasi default "Created" muncul
    // karena kita sudah menghandle notifikasi sukses di method create()
    protected function getCreatedNotification(): ?Notification
    {
        return null;
    }
}