<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'jumlah_pinjaman',
        'jangka_waktu_bulan',
        'bunga_persen_per_bulan',
        'tanggal_pengajuan',
        'tanggal_persetujuan',
        'status',
        'keterangan_pengajuan',
        'approved_by',
        'keterangan_approval',
    ];

    protected $casts = [
        'tanggal_pengajuan' => 'date',
        'tanggal_persetujuan' => 'date',
        'jumlah_pinjaman' => 'decimal:2',
        'bunga_persen_per_bulan' => 'decimal:2',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }

    // Accessor untuk Sisa Pokok Pinjaman (sudah ada)
    public function getSisaPokokPinjamanAttribute(): float // Ganti nama agar lebih jelas
    {
        $totalPembayaran = (float) $this->payments()
                                    // ->where('status', 'dikonfirmasi') // Sesuaikan jika ada kolom status di payments
                                    ->sum('jumlah_pembayaran');
        // Ini masih asumsi sederhana bahwa semua pembayaran mengurangi pokok.
        // Idealnya, pembayaran dipisah antara pokok dan bunga.
        $sisa = (float) $this->jumlah_pinjaman - $totalPembayaran;
        return $sisa > 0 ? $sisa : 0.0;
    }

    // Accessor untuk Total Estimasi Bunga Selama Periode Pinjaman (Bunga Flat Sederhana)
    public function getTotalEstimasiBungaAttribute(): float
    {
        if ($this->bunga_persen_per_bulan > 0 && $this->jangka_waktu_bulan > 0) {
            // Perhitungan bunga flat sederhana: Pokok * %bunga/bulan * jumlah bulan
            return (float) $this->jumlah_pinjaman * ($this->bunga_persen_per_bulan / 100) * $this->jangka_waktu_bulan;
        }
        return 0.0;
    }

    // Accessor untuk Total Estimasi Tagihan (Pokok + Total Estimasi Bunga)
    public function getTotalEstimasiTagihanAttribute(): float
    {
        return (float) $this->jumlah_pinjaman + $this->total_estimasi_bunga;
    }

    // Accessor untuk Sisa Estimasi Tagihan
    public function getSisaEstimasiTagihanAttribute(): float
    {
        $totalPembayaran = (float) $this->payments()
                                    // ->where('status', 'dikonfirmasi') // Sesuaikan
                                    ->sum('jumlah_pembayaran');
        $sisaTagihan = $this->total_estimasi_tagihan - $totalPembayaran;
        return $sisaTagihan > 0 ? $sisaTagihan : 0.0;
    }


    public function getTanggalAkhirPinjamanAttribute(): ?string
    {
        if ($this->tanggal_persetujuan && $this->jangka_waktu_bulan) {
            $tanggalPersetujuan = $this->tanggal_persetujuan instanceof Carbon ?
                                  $this->tanggal_persetujuan :
                                  Carbon::parse($this->tanggal_persetujuan);
            return $tanggalPersetujuan->addMonths($this->jangka_waktu_bulan)->toDateString();
        }
        return null;
    }

        public function getEstimasiCicilanBulananAttribute(): ?float
    {
        if ($this->jumlah_pinjaman > 0 && $this->jangka_waktu_bulan > 0) {
            $pokokBulanan = $this->jumlah_pinjaman / $this->jangka_waktu_bulan;
            $bungaBulanan = $this->jumlah_pinjaman * ($this->bunga_persen_per_bulan / 100); // Bunga flat dari total pokok
            // Jika bunga_persen_per_bulan adalah 0 atau null, maka hanya pokok
            if (is_null($this->bunga_persen_per_bulan) || $this->bunga_persen_per_bulan == 0) {
                return (float) $pokokBulanan;
            }
            return (float) $pokokBulanan + $bungaBulanan;
        }
        return null;
    }
}