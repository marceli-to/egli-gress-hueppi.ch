<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('total_points')->default(0);
            $table->unsignedInteger('game_points')->default(0);
            $table->unsignedInteger('special_points')->default(0);
            $table->unsignedInteger('rank')->default(0);
            $table->integer('rank_delta')->default(0);
            $table->unsignedInteger('tipp_count')->default(0);
            $table->decimal('average_score', 4, 2)->default(0);
            $table->foreignId('champion_team_id')->nullable()->constrained('teams');
            $table->timestamps();

            $table->unique(['user_id', 'tournament_id']);
            $table->index(['tournament_id', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_scores');
    }
};
