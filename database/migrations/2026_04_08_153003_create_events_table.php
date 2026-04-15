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
        Schema::create('pops', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('neighbourhood');
            $table->string('location'); // De geheime locatie
            $table->json('images')->nullable(); // Array van URLs
            $table->enum('genre', ['Meet-up', 'Cars', 'Stores', 'Bush-Party', 'Raves']);
            $table->date('date');
            $table->time('time');
            $table->enum('access', ['Public', 'Private', 'Invite-only']);
            $table->enum('event_type', ['Official', 'Unofficial']);
            $table->timestamp('reveal_time');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pops');
    }
};
