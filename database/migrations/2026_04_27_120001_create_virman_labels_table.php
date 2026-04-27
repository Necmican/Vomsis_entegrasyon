<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virman_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->foreignId('user_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->boolean('label');           // 1 = virman, 0 = değil
            $table->string('source')->default('user'); // user | retraining
            $table->timestamps();

            $table->unique(['transaction_id', 'user_id']);
            $table->index('label');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('virman_labels');
    }
};
