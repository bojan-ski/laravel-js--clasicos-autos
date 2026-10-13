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
        Schema::create('car_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('car_maker_id')->constrained()->restrictOnDelete();
            $table->foreignId('car_model_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('model');
            $table->text('description');
            $table->unsignedSmallInteger('year')->index();
            $table->unsignedInteger('mileage');
            $table->unsignedInteger('price')->index();
            $table->string('transmission');
            $table->string('fuel_type');
            $table->string('engine_size', 50)->nullable();
            $table->string('engine_type', 100)->nullable();
            $table->string('engine_history');
            $table->string('engine_condition');
            $table->string('restoration_history');
            $table->unsignedTinyInteger('original_parts_percentage')->nullable();
            $table->string('body_type')->nullable();
            $table->string('seat_material')->nullable();
            $table->string('odometer');
            $table->string('license_plate_type')->nullable();
            $table->string('documentation_status');
            $table->string('exterior_color', 50)->nullable();
            $table->string('exterior_condition')->nullable();
            $table->string('interior_color', 50)->nullable();
            $table->string('interior_condition')->nullable();
            $table->string('location_city', 100);
            $table->string('location_state', 100);
            $table->string('location_zipcode', 20);
            $table->string('status')->default('pending');
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason', 1000)->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('car_listings');
    }
};
