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
        Schema::create('user_hierarchies', function (Blueprint $table) {
            $table->unsignedBigInteger('ancestorId');
            $table->unsignedBigInteger('descendantId');
            $table->unsignedInteger('depth');
            $table->foreign('ancestorId')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('descendantId')->references('id')->on('users')->onDelete('cascade');
            $table->primary(['ancestorId', 'descendantId']); // Composite primary key
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_hierarchies');
    }
};
