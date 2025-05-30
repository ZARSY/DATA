<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Loan extends Model {
    use HasFactory;
    protected $fillable = [
        'user_id', 'jumlah_pinjaman', 'jangka_waktu_bulan', 'bunga_persen_per_bulan',
        'tanggal_pengajuan', 'tanggal_persetujuan', 'status',
        'keterangan_pengajuan', 'approved_by', 'keterangan_approval',
    ];
    protected $casts = [
        'tanggal_pengajuan' => 'date', 'tanggal_persetujuan' => 'date',
    ];
    public function user(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
}