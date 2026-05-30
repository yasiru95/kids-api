<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stories', function (Blueprint $table) {

            $table->id();

            $table->string('title');

            $table->text('description');

            $table->string('image');

            $table->string('category');

            $table->enum('age_group', [
                '3+',
                '5+',
                '8+',
                '10+',
                '13+'
            ]);

            $table->boolean('is_free')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stories');
    }
};