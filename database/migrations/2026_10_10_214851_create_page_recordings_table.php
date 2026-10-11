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
        Schema::create('page_recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('reading_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('page_number');
            $table->text('text');
            $table->string('status')->index();
            $table->string('original_path');
            $table->string('original_mime_type')->nullable();
            $table->string('processed_path')->nullable();
            $table->float('duration')->nullable();
            $table->float('trim_start')->nullable();
            $table->float('trim_end')->nullable();
            $table->text('transcript')->nullable();
            $table->json('transcript_words')->nullable();
            $table->json('word_timings')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['reading_session_id', 'page_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_recordings');
    }
};
