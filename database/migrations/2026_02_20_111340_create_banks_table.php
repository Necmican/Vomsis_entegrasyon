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
        Schema::create('banks', function (Blueprint $table) {
    $table->id(); // Bizim lokal ID'miz
    $table->string('vomsis_bank_id')->unique(); // Vomsis'ten gelen ID
    $table->string('bank_name'); // Örn: Akbank, Garanti
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};
