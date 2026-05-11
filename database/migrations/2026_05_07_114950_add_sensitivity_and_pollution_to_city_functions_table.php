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
    Schema::table('city_functions', function (Blueprint $table) {
        $table->string('sensitivity')->nullable();
        $table->string('pollution')->nullable();
    });
}

public function down()
{
    Schema::table('city_functions', function (Blueprint $table) {
        $table->dropColumn(['sensitivity', 'pollution']);
    });
}
};
