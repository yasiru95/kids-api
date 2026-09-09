<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add slug column
        Schema::table('stories', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('title');
        });

        // 2. Create slugs for existing stories
        $stories = DB::table('stories')
            ->select('id', 'title')
            ->get();

        foreach ($stories as $story) {
            DB::table('stories')
                ->where('id', $story->id)
                ->update([
                    'slug' => Str::slug($story->title),
                ]);
        }

        // 3. Make slug unique
        Schema::table('stories', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
