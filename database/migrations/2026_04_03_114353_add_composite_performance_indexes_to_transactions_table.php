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
            // 1. MAX(id) ve bakiye sorguları için kritik kompozit index
            // Bu index sayesinde "bank_account_id=5" olanların en büyük "id"sini ararken tablo taranmaz.
            $table->index(['bank_account_id', 'id'], 'idx_composite_acc_id_desc');

            // 2. İzole veri ve hesap filtrelemesi için (Global Scope + WHERE)
            $table->index(['is_real', 'bank_account_id'], 'idx_composite_real_acc');

            // 3. Çoklu aramalarda (Search) kullanılan description alanı için (Opsiyonel ama yararlı)
            // MySQL 8+ kullandığımız için prefix index ekliyoruz
            $table->index([\Illuminate\Support\Facades\DB::raw('description(100)')], 'idx_transactions_desc_prefix');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_composite_acc_id_desc');
            $table->dropIndex('idx_composite_real_acc');
            $table->dropIndex('idx_transactions_desc_prefix');
        });
    }
};
