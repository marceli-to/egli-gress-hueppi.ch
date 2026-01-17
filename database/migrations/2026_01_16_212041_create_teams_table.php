<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('nation_id')->constrained();
            $table->char('group_name', 1);
            $table->unsignedTinyInteger('points')->default(0);
            $table->unsignedTinyInteger('goals_for')->default(0);
            $table->unsignedTinyInteger('goals_against')->default(0);
            $table->unsignedTinyInteger('wins')->default(0);
            $table->unsignedTinyInteger('draws')->default(0);
            $table->unsignedTinyInteger('losses')->default(0);
            $table->unsignedSmallInteger('fair_play_points')->default(0);
            $table->timestamps();

            $table->index(['tournament_id', 'group_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
