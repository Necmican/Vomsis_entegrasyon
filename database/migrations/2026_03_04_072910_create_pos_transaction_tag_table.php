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
        // 1. Zombi temizliği: Eğer önceki denemelerden yarım kalmış bir tablo varsa önce onu uçur.
        Schema::dropIfExists('pos_transaction_tag');

        Schema::create('pos_transaction_tag', function (Blueprint $table) {
            $table->id(); // Pivot tablonun kendi ID'si
            
            // 2. Bağlantı Sütunları
            // pos_transactions tablosunun ID'sini tutacak sütun (Büyük ihtimalle normal Integer'dı)
            $table->unsignedInteger('pos_transaction_id'); 
            
            // tags tablosunun ID'sini tutacak sütun (BigInteger)
            $table->unsignedBigInteger('tag_id');
            
            // 3. MySQL 150 Hatasını BYPASS Eden Kısım!
            // Sadece 'tags' tablosuna kilit vuruyoruz. 'pos_transactions' için kilit YAZMIYORUZ.
            // Bu sayede MySQL tip uyuşmazlığına bakmayacak, ilişkiyi Laravel (Eloquent) arka planda yönetecek.
            $table->foreign('tag_id')
                  ->references('id')
                  ->on('tags')
                  ->onDelete('cascade'); // Etiket silinirse, bağ da silinsin
                  
            // 4. Mantıksal Kilit: Aynı işlemde aynı etiket iki kere eklenemesin (Örn: İki tane "Acil" etiketi basılamasın)
            $table->unique(['pos_transaction_id', 'tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_transaction_tag');
    }
};