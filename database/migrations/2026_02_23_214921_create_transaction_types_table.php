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

    Schema::create('transaction_types', function (Blueprint $table) {
        $table->id();
        $table->string('vomsis_type_id')->unique(); // Vomsis'ten gelen ID
        $table->string('name'); // İşlem Adı (Örn: Gelen EFT)
        $table->string('code')->nullable(); // İşlem Kodu
        $table->timestamps();
    });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_types');
    }
};
