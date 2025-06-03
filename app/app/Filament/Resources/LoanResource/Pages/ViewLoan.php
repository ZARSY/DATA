<?php

namespace App\Filament\Resources\LoanResource\Pages;

use App\Filament\Resources\LoanResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use App\Models\Loan; // <-- PASTIKAN MODEL LOAN DIIMPOR DENGAN BENAR

class ViewLoan extends ViewRecord
{
    protected static string $resource = LoanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Informasi Peminjam')
                    ->schema([
                        Components\TextEntry::make('user.name')->label('Nama Peminjam'),
                        Components\TextEntry::make('user.nomor_anggota')->label('Nomor Anggota'),
                    ])->columns(2),

                Components\Section::make('Detail Pinjaman')
                    ->schema([
                        Components\TextEntry::make('jumlah_pinjaman')->money('IDR', true)->label('Jumlah Pokok Pinjaman'),
                        Components\TextEntry::make('jangka_waktu_bulan')->label('Jangka Waktu')->suffix(' bulan'),
                        Components\TextEntry::make('bunga_persen_per_bulan')->label('Bunga Disetujui')->suffix(' % / bulan'),
                        Components\TextEntry::make('total_estimasi_bunga')
                            ->label('Total Estimasi Bunga')
                            ->money('IDR', true)
                            // Jika ada getStateUsing di sini, pastikan $record bertipe Loan
                            ->state(fn (Loan $record): float => $record->total_estimasi_bunga), // Contoh

                        Components\TextEntry::make('total_estimasi_tagihan')
                            ->label('Total Estimasi Tagihan (Pokok + Bunga)')
                            ->money('IDR', true)
                            ->state(fn (Loan $record): float => $record->total_estimasi_tagihan), // Contoh

                        Components\TextEntry::make('estimasi_cicilan_bulanan')
                            ->label('Estimasi Cicilan per Bulan (Flat)')
                            ->money('IDR', true)
                            // Pastikan $record di sini bertipe App\Models\Loan
                            ->visible(fn (Loan $record) => $record->status === 'disetujui' || $record->status === 'berjalan')
                            ->state(fn (Loan $record): ?float => $record->estimasi_cicilan_bulanan), // Contoh

                        Components\TextEntry::make('sisa_estimasi_tagihan')
                            ->label('Sisa Estimasi Tagihan')
                            ->money('IDR', true)
                            // Pastikan $record di sini bertipe App\Models\Loan
                            ->color(fn ($state, Loan $record) => $record->sisa_estimasi_tagihan > 0 ? 'warning' : 'success') // $state adalah sisa_estimasi_tagihan
                            ->state(fn (Loan $record): float => $record->sisa_estimasi_tagihan), // Contoh

                        Components\TextEntry::make('tanggal_pengajuan')->date(),
                        Components\TextEntry::make('status')->badge()
                             // Pastikan $record di sini bertipe App\Models\Loan
                            ->color(fn (string $state, Loan $record): string => match ($state) {
                                'diajukan' => 'warning',
                                'disetujui' => 'success',
                                'ditolak' => 'danger',
                                'berjalan' => 'info',
                                'lunas' => 'primary',
                                default => 'gray',
                            }),
                    ])->columns(2),

                Components\Section::make('Informasi Persetujuan')
                    ->schema([
                        Components\TextEntry::make('approver.name')->label('Disetujui Oleh'),
                        Components\TextEntry::make('tanggal_persetujuan')->date(),
                        Components\TextEntry::make('keterangan_approval')->label('Keterangan Persetujuan/Penolakan'),
                    ])->columns(2)
                    // Pastikan $record di sini bertipe App\Models\Loan
                    ->visible(fn (?Loan $record) => $record && !in_array($record->status, ['diajukan'])),
            ]);
    }
}