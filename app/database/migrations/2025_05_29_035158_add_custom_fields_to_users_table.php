<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Kolom 'role' TIDAK ADA di sini.
            $table->string('nomor_anggota')->unique()->nullable()->after('email');
            $table->text('alamat')->nullable()->after('nomor_anggota');
            $table->string('telepon')->nullable()->after('alamat');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['nomor_anggota', 'alamat', 'telepon']);
        });
    }
};