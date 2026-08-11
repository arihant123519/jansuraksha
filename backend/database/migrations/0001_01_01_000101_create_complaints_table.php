<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->string('complaint_id', 20)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('vehicle_number', 15);
            $table->enum('violation_type', [
                'signal_jump','wrong_side','no_helmet','triple_riding',
                'phone_while_driving','no_seatbelt','overloading','others',
            ]);
            $table->timestamp('reported_at');
            $table->geometry('location', 'point');   // MySQL POINT for spatial queries (NOT NULL required for spatial index)
            $table->decimal('location_lat', 10, 7);
            $table->decimal('location_lng', 10, 7);
            $table->string('area_state', 100)->nullable();
            $table->string('area_district', 100)->nullable();
            $table->string('area_taluka', 100)->nullable();
            $table->enum('status', [
                'submitted','under_review','actioned','rejected','duplicate','pending',
            ])->default('submitted');
            $table->boolean('is_flagged')->default(false);
            $table->timestamps();

            $table->index('vehicle_number');
            $table->index('violation_type');
            $table->index('reported_at');
            $table->index('area_district');
            $table->index('status');
            $table->index('is_flagged');
            $table->spatialIndex('location');
        });
    }
    public function down(): void { Schema::dropIfExists('complaints'); }
};