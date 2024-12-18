<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateBetsTable extends Migration
{
    public function up()
    {
        Schema::table('bets', function (Blueprint $table) {
            $table->renameColumn('matrix', 'data');
            $table->string('trade_id')->nullable()->change();
            $table->enum('type', ['slot', 'live']);
            $table->string('provider');
        });
    }

    public function down()
    {
        Schema::table('bets', function (Blueprint $table) {
            $table->renameColumn('data', 'matrix');
            $table->string('trade_id')->nullable(false)->change();
            $table->dropColumn('type');
            $table->dropColumn('provider');
        });
    }
}
