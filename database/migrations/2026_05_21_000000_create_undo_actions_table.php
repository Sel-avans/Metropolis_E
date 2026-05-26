<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('undo_actions');

        Schema::create('undo_actions', function (Blueprint $table) {
            $table->id();
            $table->integer('row');
            $table->integer('col');
            $table->integer('new_row')->nullable(); 
            $table->integer('new_col')->nullable(); 
            $table->unsignedBigInteger('previous_function_id')->nullable();
            $table->string('action_type');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('undo_actions');
    }
};