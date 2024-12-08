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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->float('amount');
            $table->enum('type', ['deposit', 'withdraw']);
            $table->unsignedBigInteger('fromUserId');
            $table->unsignedBigInteger('toUserId');
            $table->string('fromRole');
            $table->string('toRole');
            $table->string('fromUsername');
            $table->string('toUsername');
            $table->timestamp('date')->useCurrent();

            // Foreign keys
            $table->foreign('fromUserId')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('toUserId')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
