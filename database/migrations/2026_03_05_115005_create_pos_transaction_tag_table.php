<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_transaction_tag', function (Blueprint $table) {
            $table->id();
            
            // DÜZELTİLDİ: 'pos_transactions' yerine 'transactions' tablosuna bağlandı!
            $table->foreignId('pos_transaction_id')
                  ->constrained('transactions') 
                  ->onDelete('cascade'); 
                  
            // Etiket ID'si (tags tablosuna bağlı)
            $table->foreignId('tag_id')
                  ->constrained('tags')
                  ->onDelete('cascade'); 
                  
            $table->unique(['pos_transaction_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_transaction_tag');
    }
};