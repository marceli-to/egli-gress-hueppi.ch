<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_tipps', function (Blueprint $table) {
            $table->index('score');
        });

        Schema::table('games', function (Blueprint $table) {
            $table->index(['tournament_id', 'is_finished']);
        });

        Schema::table('user_scores', function (Blueprint $table) {
            $table->index(['tournament_id', 'total_points']);
        });
    }

    public function down(): void
    {
        Schema::table('game_tipps', function (Blueprint $table) {
            $table->dropIndex(['score']);
        });

        Schema::table('games', function (Blueprint $table) {
            $table->dropIndex(['tournament_id', 'is_finished']);
        });

        Schema::table('user_scores', function (Blueprint $table) {
            $table->dropIndex(['tournament_id', 'total_points']);
        });
    }
};
