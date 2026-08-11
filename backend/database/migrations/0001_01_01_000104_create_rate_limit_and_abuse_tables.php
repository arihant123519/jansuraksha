<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('rate_limit_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('device_fingerprint', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->date('report_date');
            $table->unsignedTinyInteger('count')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'report_date']);
            $table->index('device_fingerprint');
        });

        Schema::create('vehicle_abuse_flags', function (Blueprint $table) {
            $table->id();
            $table->string('vehicle_number', 15);
            $table->unsignedSmallInteger('report_count')->default(0);
            $table->date('window_start');
            $table->boolean('admin_reviewed')->default(false);
            $table->text('admin_notes')->nullable();
            $table->timestamps();
            $table->unique(['vehicle_number', 'window_start']);
            $table->index('admin_reviewed');
        });
    }
    public function down(): void {
        Schema::dropIfExists('vehicle_abuse_flags');
        Schema::dropIfExists('rate_limit_tracking');
    }
};