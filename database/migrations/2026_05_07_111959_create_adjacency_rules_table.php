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
    Schema::create('adjacency_rules', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('function_a');
        $table->unsignedBigInteger('function_b');
        $table->enum('type', ['bonus', 'penalty', 'forbidden']);
        $table->integer('value');
        $table->timestamps();

        $table->foreign('function_a')->references('id')->on('city_functions')->onDelete('cascade');
        $table->foreign('function_b')->references('id')->on('city_functions')->onDelete('cascade');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adjacency_rules');
    }
};
