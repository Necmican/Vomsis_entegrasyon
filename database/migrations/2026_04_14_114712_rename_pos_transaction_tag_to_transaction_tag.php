<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot tabloyu ve sütununu doğru isimlendirmeye taşır.
     * pos_transaction_tag → transaction_tag
     * pos_transaction_id → transaction_id
     */
    public function up(): void
    {
        // 1. Sütun adını değiştir (tablo henüz eski adıyla duruyorken)
        Schema::table('pos_transaction_tag', function (Blueprint $table) {
            $table->renameColumn('pos_transaction_id', 'transaction_id');
        });

        // 2. Tablo adını değiştir
        Schema::rename('pos_transaction_tag', 'transaction_tag');
    }

    /**
     * Geri alma: Her şeyi eski haline döndürür.
     */
    public function down(): void
    {
        Schema::rename('transaction_tag', 'pos_transaction_tag');

        Schema::table('pos_transaction_tag', function (Blueprint $table) {
            $table->renameColumn('transaction_id', 'pos_transaction_id');
        });
    }
};
