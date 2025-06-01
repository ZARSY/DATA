<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id',
        'user_id', // Anggota pemilik pinjaman
        'jumlah_pembayaran',
        'tanggal_pembayaran',
        'metode_pembayaran',
        'keterangan',
        'processed_by', // Teller/Admin yang memproses
        'status',
        'bukti_transfer',     // <-- TAMBAHKAN BARIS INI
    ];

    protected $casts = [
        'tanggal_pembayaran' => 'date',
    ];

    // Relasi ke Pinjaman
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    // Anggota pemilik pinjaman ini
    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // User (Teller/Admin) yang memproses pembayaran
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}