<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Tambahkan kolom 'status' setelah kolom 'processed_by' (atau sesuaikan posisinya jika perlu)
            // Beri nilai default 'dikonfirmasi' agar data lama yang mungkin sudah ada tidak error,
            // atau Anda bisa membiarkannya nullable() jika lebih sesuai.
            $table->string('status')->default('dikonfirmasi')->after('processed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};