<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->enum('game_type', [
                'GROUP', 'ROUND_OF_16', 'QUARTER_FINAL',
                'SEMI_FINAL', 'THIRD_PLACE', 'FINAL'
            ]);
            $table->char('group_name', 1)->nullable();
            $table->timestamp('kickoff_at');
            $table->foreignId('location_id')->constrained();
            $table->foreignId('home_team_id')->nullable()->constrained('teams');
            $table->foreignId('visitor_team_id')->nullable()->constrained('teams');
            $table->string('home_team_placeholder', 50)->nullable();
            $table->string('visitor_team_placeholder', 50)->nullable();
            $table->unsignedTinyInteger('goals_home')->nullable();
            $table->unsignedTinyInteger('goals_visitor')->nullable();
            $table->unsignedTinyInteger('goals_home_halftime')->nullable();
            $table->unsignedTinyInteger('goals_visitor_halftime')->nullable();
            $table->boolean('is_finished')->default(false);
            $table->boolean('has_penalty_shootout')->default(false);
            $table->foreignId('penalty_winner_team_id')->nullable()->constrained('teams');
            $table->timestamps();

            $table->index(['tournament_id', 'game_type']);
            $table->index('kickoff_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
