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
        Schema::create('physical_pos_transactions', function (Blueprint $table) {
            $table->id();
            
            // İLİŞKİ: Bu işlem HANGİ cihaza ait? Cihaz silinirse işlemleri de sil (cascade).
            $table->foreignId('physical_pos_id')->constrained('physical_poses')->onDelete('cascade');
            
            // VOMSIS API VERİLERİ (Senin attığın dokümana göre)
            $table->unsignedBigInteger('vomsis_transaction_id')->unique(); // API'deki "id". Aynı işlemi 2 kere çekmemek için
            $table->string('transaction_key')->nullable(); // API'deki "key" değeri
            
            // Kart Bilgileri
            $table->string('card_number')->nullable(); // Örn: 1234********6789
            $table->string('card_type')->nullable(); // Örn: VISA
            $table->string('sub_card_type')->nullable(); // Örn: MASTERCARD
            
            // Finansal Veriler (Parasal değerler için decimal kullanıyoruz)
            $table->decimal('gross_amount', 15, 2)->default(0); // Brüt tutar (Müşteriden çekilen)
            $table->decimal('commission', 15, 2)->default(0); // Kesilen komisyon tutarı
            $table->decimal('commission_rate', 5, 2)->default(0); // Komisyon oranı (%)
            $table->decimal('net_amount', 15, 2)->default(0); // Net tutar (Hesaba geçecek para)
            $table->string('exchange')->nullable(); // Döviz cinsi (Örn: EUR, TL)
            
            // İşlem Detayları
            $table->integer('installments_count')->default(0); // Taksit sayısı
            $table->string('transaction_type')->nullable(); // Örn: Satış
            $table->string('description')->nullable(); // Örn: PESIN YD KART
            
            // Referans ve Takip Numaraları
            $table->string('confirmation_number')->nullable();
            $table->string('provision_no')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('batchn')->nullable(); // Batch numarası
            $table->string('workplace')->nullable(); // İşyeri numarası
            $table->string('station')->nullable(); // Terminal (POS) numarası
            
            // Tarihler
            $table->string('valor')->nullable(); // Valör tarihi (Paranın geçeceği gün)
            $table->dateTime('system_date')->nullable(); // Sisteme kayıt saati (Ana referansımız)
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('physical_pos_transactions');
    }
};
