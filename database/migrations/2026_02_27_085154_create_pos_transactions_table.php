<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('pos_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('virtual_pos_id')->constrained('virtual_poses')->onDelete('restrict');
            $table->string('order_id')->unique();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('TL');
            $table->integer('installments')->default(1);
            $table->string('card_mask', 20)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('response_code', 10)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('transaction_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_transactions');
    }
};
