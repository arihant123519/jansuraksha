<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('complaint_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->cascadeOnDelete();
            $table->unsignedBigInteger('changed_by');   // police_users.id
            $table->string('old_status', 30);
            $table->string('new_status', 30);
            $table->string('reason', 255)->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();
            $table->index('complaint_id');
            $table->index('changed_at');
        });
    }
    public function down(): void { Schema::dropIfExists('complaint_status_logs'); }
};