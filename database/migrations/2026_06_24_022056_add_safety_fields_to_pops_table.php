<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pops', function (Blueprint $table) {
            // Voegt de kolommen toe en zet ze standaard op 'false' (nee)
            $table->boolean('has_first_aider')->default(false)->after('is_active');
            $table->boolean('has_security')->default(false)->after('has_first_aider');
        });
    }

    public function down(): void
    {
        Schema::table('pops', function (Blueprint $table) {
            $table->dropColumn(['has_first_aider', 'has_security']);
        });
    }
};
