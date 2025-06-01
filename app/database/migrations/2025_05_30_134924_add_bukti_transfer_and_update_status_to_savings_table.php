<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('savings', function (Blueprint $table) {
            $table->string('bukti_transfer')->nullable()->after('keterangan'); // Untuk menyimpan path file
            // Ubah kolom status untuk mengakomodasi alur approval
            // Hapus default lama jika ada, dan buat ulang dengan enum atau string baru
            // Jika sebelumnya $table->string('status')->default('pending');
            // Kita bisa drop dan add, atau modify. Modify lebih aman jika ada data.
            // Untuk contoh ini, kita asumsikan bisa drop dan add jika belum ada data penting
            // atau Anda bisa membuat migrasi terpisah untuk mengubah tipe kolom.
            // Pilihan: ubah tipe status yang sudah ada atau tambahkan kolom status_approval
            // Mari kita modifikasi kolom status yang ada
            $table->string('status')->default('pending_approval')->comment('pending_approval, dikonfirmasi, ditolak')->change();
        });
    }

    public function down(): void
    {
        Schema::table('savings', function (Blueprint $table) {
            $table->dropColumn('bukti_transfer');
            // Kembalikan status ke definisi sebelumnya jika perlu, atau biarkan
            $table->string('status')->default('pending')->change(); // Sesuaikan dengan default sebelumnya
        });
    }
};