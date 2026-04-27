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
        Schema::create('physical_poses', function (Blueprint $table) {
            $table->id(); // Bizim sistemin kendi ID'si
            
            
            $table->unsignedBigInteger('vomsis_id')->unique(); 
            $table->string('bank_name')->nullable(); 
            $table->string('bank_title')->nullable(); 
            $table->boolean('status')->default(true); 
            $table->string('workplace_no')->nullable(); 
            $table->string('station_no')->nullable(); 
            $table->string('workplace_name')->nullable(); 
            $table->string('transaction_currency')->default('TL'); 
            $table->string('custom_name')->nullable(); 
            $table->decimal('commission_rate', 5, 2)->default(0.00); 
            
            $table->timestamps(); // Ne zaman senkronize edildi (created_at, updated_at)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('physical_pos');
    }
};
