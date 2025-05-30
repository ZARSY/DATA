<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('jumlah_pinjaman', 15, 2);
            $table->integer('jangka_waktu_bulan');
            $table->decimal('bunga_persen_per_bulan', 5, 2)->default(0);
            $table->date('tanggal_pengajuan');
            $table->date('tanggal_persetujuan')->nullable();
            $table->string('status')->default('diajukan'); // diajukan, disetujui, ditolak, berjalan, lunas
            $table->text('keterangan_pengajuan')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('keterangan_approval')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('loans');
    }
};