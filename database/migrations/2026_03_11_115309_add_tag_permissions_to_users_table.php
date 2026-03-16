<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Etiket üretebilir mi? (Evet/Hayır)
            $table->boolean('can_create_tags')->default(false)->after('can_view_physical_pos');
            
            // Hangi etiketleri görebilir? (Tıpkı allowed_banks gibi JSON formatında ID'leri tutacak)
            $table->json('allowed_tags')->nullable()->after('can_create_tags');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['can_create_tags', 'allowed_tags']);
        });
    }
};