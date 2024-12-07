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
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique();
            $table->float('balance')->default(0);
            $table->unsignedBigInteger('parentId')->nullable();
            $table->unsignedBigInteger('roleId');
            $table->boolean('status')->default(true);
            $table->timestamp('last_accessed')->nullable();
            $table->foreign('roleId')->references('id')->on('roles')->onDelete('CASCADE');
            $table->foreign('parentId')->references('id')->on('users')->onDelete('SET NULL');
            $table->string('email')->nullable()->change();
            $table->string('name')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['roleId']);
            $table->dropForeign(['parentId']);
            $table->dropColumn([
                'username',
                'balance',
                'parentId',
                'roleId',
                'status',   
                'last_accessed',
            ]);
            $table->string('email')->nullable(false)->change();
            $table->string('name')->nullable(false)->change();
        });
    }
};
