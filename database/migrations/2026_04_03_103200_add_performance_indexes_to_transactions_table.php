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
        Schema::table('transactions', function (Blueprint $table) {
            // 1. Ana sorgu filtresi: is_real (Gerçek/Demo veri izolasyonu)
            $table->index('is_real', 'idx_transactions_is_real');

            // 2. Sıralama ve tarih filtreleri için
            $table->index('transaction_date', 'idx_transactions_date');

            // 3. Banka hesabı ilişkisi (JOIN/WHERE hızlandırma)
            $table->index('bank_account_id', 'idx_transactions_bank_account');

            // 4. Çoklu sütun indexi: is_real + transaction_date (en sık kullanılan sorgu deseni)
            $table->index(['is_real', 'transaction_date'], 'idx_transactions_real_date');

            // 5. Çoklu sütun indexi: bank_account_id + transaction_date (hesap bakiyesi sorguları)
            $table->index(['bank_account_id', 'transaction_date'], 'idx_transactions_account_date');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_transactions_is_real');
            $table->dropIndex('idx_transactions_date');
            $table->dropIndex('idx_transactions_bank_account');
            $table->dropIndex('idx_transactions_real_date');
            $table->dropIndex('idx_transactions_account_date');
        });
    }
};
