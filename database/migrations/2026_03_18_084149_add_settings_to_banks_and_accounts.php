<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Bankalar Tablosuna Görünürlük Ayarı
        Schema::table('banks', function (Blueprint $table) {
            $table->boolean('is_visible')->default(true)->after('bank_name'); 
            // default(true): Varsayılan olarak tüm bankalar açık gelsin.
        });

        // 2. Hesaplar Tablosuna Görünürlük ve Kasaya Dahil Etme Ayarı
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->boolean('is_visible')->default(true)->after('currency');
            $table->boolean('include_in_totals')->default(true)->after('is_visible');
            // include_in_totals: İşlemlerin parası üstteki "Toplam Bakiye" kartlarına yansısın mı?
        });
    }

    public function down(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->dropColumn('is_visible');
        });
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropColumn(['is_visible', 'include_in_totals']);
        });
    }
};