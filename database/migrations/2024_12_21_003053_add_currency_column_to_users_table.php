<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCurrencyColumnToUsersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('currency', ['USD', 'LBP'])->default('LBP')->after('last_accessed');
        });

        // Optionally, for any already existing users, they will automatically
        // get 'LBP' because of the default value. If you have existing records
        // and want to ensure consistency, you can update them here:
        //
        DB::table('users')->whereNull('currency')->update(['currency' => 'LBP']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
}
