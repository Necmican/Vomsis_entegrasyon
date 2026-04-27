<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Sanal pos sütununun hemen yanına Fiziksel POS yetkisini ekliyoruz
            $table->boolean('can_view_physical_pos')->default(false)->after('can_view_pos');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('can_view_physical_pos');
        });
    }
};