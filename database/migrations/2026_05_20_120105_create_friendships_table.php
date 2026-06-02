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
        Schema::create('friendships', function (Blueprint $table) {
            $table->id();
            // De gebruiker die de ander heeft toegevoegd/is bevriend
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            // De vriend zelf
            $table->foreignId('friend_id')->constrained('users')->onDelete('cascade');
            // Status: handig voor vriendschapsverzoeken ('pending', 'accepted')
            $table->string('status')->default('accepted');
            $table->timestamps();

            // Zorg dat een vriendschap maar één keer kan bestaan in de database
            $table->unique(['user_id', 'friend_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('friendships');
    }
};
