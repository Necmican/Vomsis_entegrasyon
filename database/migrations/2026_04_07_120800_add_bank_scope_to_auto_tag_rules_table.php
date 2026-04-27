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
        Schema::table('auto_tag_rules', function (Blueprint $table) {
            $table->unsignedBigInteger('bank_id')->nullable()->after('tag_id');
            $table->unsignedBigInteger('bank_account_id')->nullable()->after('bank_id');

            $table->foreign('bank_id')->references('id')->on('banks')->onDelete('cascade');
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auto_tag_rules', function (Blueprint $table) {
            $table->dropForeign(['bank_id']);
            $table->dropForeign(['bank_account_id']);
            $table->dropColumn('bank_id');
            $table->dropColumn('bank_account_id');
        });
    }
};
