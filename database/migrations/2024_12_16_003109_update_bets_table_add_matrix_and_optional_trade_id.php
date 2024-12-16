<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateBetsTableAddMatrixAndOptionalTradeId extends Migration
{
    public function up()
    {
        Schema::table('bets', function (Blueprint $table) {
            $table->string('trade_id')->nullable()->change();
            $table->text('matrix')->nullable()->after('win_amount');
        });
    }

    public function down()
    {
        Schema::table('bets', function (Blueprint $table) {
            $table->string('trade_id')->nullable(false)->change();
            $table->dropColumn('matrix');
        });
    }
}
