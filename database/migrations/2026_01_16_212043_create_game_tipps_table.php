<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_tipps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('goals_home');
            $table->unsignedTinyInteger('goals_visitor');
            $table->foreignId('penalty_winner_team_id')->nullable()->constrained('teams');
            $table->unsignedTinyInteger('score')->default(0);
            $table->boolean('is_tendency_correct')->default(false);
            $table->boolean('is_difference_correct')->default(false);
            $table->boolean('is_goals_home_correct')->default(false);
            $table->boolean('is_goals_visitor_correct')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'game_id']);
            $table->index('game_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_tipps');
    }
};
