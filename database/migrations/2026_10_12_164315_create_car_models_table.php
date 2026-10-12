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
        Schema::create('car_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_maker_id')->constrained()->restrictOnDelete();
            $table->string('name', 64);
            $table->unsignedSmallInteger('year_from');
            $table->unsignedSmallInteger('year_to');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['car_maker_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('car_models');
    }
};
