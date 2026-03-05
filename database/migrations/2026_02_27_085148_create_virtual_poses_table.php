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
        Schema::create('virtual_poses', function (Blueprint $table) {
            $table->id(); 
            $table->foreignId('bank_id')->constrained('banks')->onDelete('cascade');
            $table->string('name');
            $table->string('merchant_id');
            $table->string('terminal_id')->nullable();
            $table->string('api_key')->nullable();
            $table->string('api_secret')->nullable();
            $table->string('currency', 3)->default('TL');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('virtual_poses');
    }
};
