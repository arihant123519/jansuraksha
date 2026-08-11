<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('evidence_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->cascadeOnDelete();
            $table->enum('file_type', ['photo', 'video']);
            $table->string('s3_path', 512);
            $table->string('thumbnail_s3_path', 512)->nullable();
            $table->timestamp('uploaded_at');
            $table->timestamps();
            $table->index('complaint_id');
        });
    }
    public function down(): void { Schema::dropIfExists('evidence_files'); }
};