<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('is_virman')->default(false)->after('type');
            $table->decimal('virman_confidence', 5, 2)->nullable()->after('is_virman');
            $table->enum('virman_method', ['PAIR', 'CODE', 'ML', 'MANUAL', 'NONE'])
                  ->default('NONE')->after('virman_confidence');
            $table->unsignedBigInteger('transfer_pair_id')->nullable()->after('virman_method');

            $table->index('is_virman', 'idx_is_virman');
            $table->index('transfer_pair_id', 'idx_transfer_pair_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_is_virman');
            $table->dropIndex('idx_transfer_pair_id');
            $table->dropColumn(['is_virman', 'virman_confidence', 'virman_method', 'transfer_pair_id']);
        });
    }
};
