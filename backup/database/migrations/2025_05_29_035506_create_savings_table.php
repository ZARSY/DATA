<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('jenis_simpanan'); // e.g., pokok, wajib, sukarela
            $table->decimal('jumlah', 15, 2);
            $table->date('tanggal_transaksi');
            $table->string('status')->default('pending'); // e.g., pending, dikonfirmasi
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('savings');
    }
};