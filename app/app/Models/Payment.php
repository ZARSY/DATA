<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Payment extends Model {
    use HasFactory;
    protected $fillable = [
        'loan_id', 'user_id', 'jumlah_pembayaran', 'tanggal_pembayaran',
        'metode_pembayaran', 'keterangan', 'processed_by',
    ];
    protected $casts = [
        'tanggal_pembayaran' => 'date',
    ];
    public function loan(): BelongsTo { return $this->belongsTo(Loan::class); }
    public function member(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function processor(): BelongsTo { return $this->belongsTo(User::class, 'processed_by'); }
}