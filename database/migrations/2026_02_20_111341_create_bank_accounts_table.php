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
       Schema::create('bank_accounts', function (Blueprint $table) {
    $table->id();
    $table->string('vomsis_account_id')->unique(); // Vomsis'ten gelen hesap ID'si
    $table->foreignId('bank_id')->constrained('banks')->onDelete('cascade'); // Hangi bankaya ait?
    $table->string('iban')->nullable();
    $table->string('account_name')->nullable();
    $table->string('currency', 10)->default('TRY'); // TRY, USD, EUR
    $table->decimal('balance', 15, 2)->default(0); // Bakiye
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
