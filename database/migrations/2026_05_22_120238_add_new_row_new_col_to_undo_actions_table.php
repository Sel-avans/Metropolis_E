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
    Schema::table('undo_actions', function (Blueprint $table) {
        $table->integer('new_row')->nullable();
        $table->integer('new_col')->nullable();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down()
{
    Schema::table('undo_actions', function (Blueprint $table) {
        $table->dropColumn(['new_row', 'new_col']);
    });
}

};
