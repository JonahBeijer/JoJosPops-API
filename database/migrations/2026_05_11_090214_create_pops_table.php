<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pops', function (Blueprint $table) {
            $table->id();

            // 🔑 REPARATIE 1: De onmisbare koppeling met de users tabel
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->string('title');
            $table->string('neighbourhood');
            $table->string('location')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // 🔑 REPARATIE 2: Toegevoegd omdat ProfileController hier specifiek naar zoekt
            $table->string('image_emoji')->nullable()->default('🍿');

            $table->string('genre')->nullable();
            $table->text('description')->nullable();
            $table->integer('capacity')->default(0);
            $table->integer('current_guests')->default(0);

            $table->date('date')->nullable();
            $table->string('event_time')->nullable();
            $table->string('access')->nullable();
            $table->string('event_type')->nullable();

            $table->json('images')->nullable();
            $table->timestamp('reveal_time')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pops');
    }
};
