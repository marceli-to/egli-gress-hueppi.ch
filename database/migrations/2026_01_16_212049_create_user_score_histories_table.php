<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_score_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('game_day');
            $table->unsignedInteger('points')->default(0);
            $table->unsignedInteger('rank')->default(0);
            $table->integer('rank_delta')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'tournament_id']);
            $table->index(['tournament_id', 'game_day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_score_histories');
    }
};
