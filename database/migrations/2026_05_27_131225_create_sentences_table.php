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
    Schema::create('sentences', function (Blueprint $table) {
        $table->id();

        $table->foreignId('story_page_id')
              ->constrained()
              ->onDelete('cascade');

        $table->text('text');

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sentences');
    }
};
