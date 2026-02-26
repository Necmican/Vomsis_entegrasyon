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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vomsis_transaction_id')->unique();
            $table->unsignedBigInteger('bank_account_id');
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            
            // Sonradan eklediğimiz ama ana plana dahil ettiğimiz tüm sütunlar:
            $table->string('transaction_type_code')->nullable(); 
            $table->string('type')->nullable(); 
            $table->decimal('balance', 15, 2)->default(0); // <-- İŞTE PATLAYAN SÜTUN BURADA!
            
            $table->dateTime('transaction_date')->nullable();
            $table->timestamps();

            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->onDelete('cascade');
        });
    }
    

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
