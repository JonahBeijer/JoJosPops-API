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
        Schema::table('users', function (Blueprint $table) {
            // Voeg de device_token kolom toe. Nullable, want niet iedereen heeft direct een token.
            $table->string('device_token')->nullable()->after('password');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            // Haal de kolom weer weg als de migratie wordt teruggedraaid
            $table->dropColumn('device_token');
        });
    }
};
