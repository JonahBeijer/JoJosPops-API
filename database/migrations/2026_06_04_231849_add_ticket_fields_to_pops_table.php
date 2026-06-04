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
        Schema::table('pops', function (Blueprint $table) {
            // Voeg de velden toe (bijv. na 'reveal_time' of helemaal onderaan)
            $table->boolean('is_ticketed')->default(false)->after('reveal_time');
            $table->decimal('ticket_price', 8, 2)->nullable()->after('is_ticketed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pops', function (Blueprint $table) {
            // Verwijder de velden weer als we de migratie terugdraaien
            $table->dropColumn(['is_ticketed', 'ticket_price']);
        });
    }
};
