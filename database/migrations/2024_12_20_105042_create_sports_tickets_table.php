<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sports_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->uuid('transaction_id')->unique();
            $table->decimal('amount', 15, 2);
            $table->decimal('win_amount', 15, 2)->default(0);
            $table->string('currency', 5);
            $table->string('type', 20);
            $table->string('game_type', 50);
            $table->string('settle_status', 20)->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sports_tickets');
    }
};
