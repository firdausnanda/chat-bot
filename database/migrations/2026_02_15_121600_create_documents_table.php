<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('documents', function (Blueprint $table) {
      $table->id();
      $table->string('filename');
      $table->string('filepath');
      $table->unsignedBigInteger('file_size')->default(0);
      $table->unsignedInteger('pages_count')->nullable();
      $table->unsignedInteger('chunks_count')->nullable();
      $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('documents');
  }
};
