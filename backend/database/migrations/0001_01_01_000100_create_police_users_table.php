<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('police_users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique();
            $table->string('password_hash');
            $table->string('name', 100);
            $table->string('badge_number', 30)->unique();
            $table->string('jurisdiction_state', 100)->nullable();
            $table->string('jurisdiction_district', 100)->nullable();
            $table->enum('role', ['officer', 'supervisor', 'admin'])->default('officer');
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->index(['jurisdiction_state', 'jurisdiction_district']);
        });
    }
    public function down(): void { Schema::dropIfExists('police_users'); }
};