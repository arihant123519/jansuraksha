<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('mobile_number', 10)->unique();
            $table->timestamp('otp_verified_at')->nullable();
            $table->unsignedTinyInteger('daily_report_count')->default(0);
            $table->timestamp('last_reset_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('users'); }
};