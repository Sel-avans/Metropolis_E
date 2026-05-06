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
    Schema::table('effects', function (Blueprint $table) {
        $table->renameColumn('city_function_id', 'function_id');
    });
}

public function down()
{
    Schema::table('effects', function (Blueprint $table) {
        $table->renameColumn('function_id', 'city_function_id');
    });
}
};
