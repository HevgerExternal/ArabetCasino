<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropSportsTicketsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('sports_tickets');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::create('sports_tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('transaction_id');
            $table->decimal('amount', 10, 2);
            $table->decimal('win_amount', 10, 2)->default(0);
            $table->string('currency', 5);
            $table->string('game_type');
            $table->string('settle_status')->default('unsettled');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }
}
