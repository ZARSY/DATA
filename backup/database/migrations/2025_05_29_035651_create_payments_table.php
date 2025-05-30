<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->foreignId('user_id')->comment('Anggota pemilik pinjaman')->constrained('users')->cascadeOnDelete();
            $table->decimal('jumlah_pembayaran', 15, 2);
            $table->date('tanggal_pembayaran');
            $table->string('metode_pembayaran')->nullable();
            $table->text('keterangan')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete(); // Teller/Admin
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('payments');
    }
};