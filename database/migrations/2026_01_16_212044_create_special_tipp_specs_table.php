<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('special_tipp_specs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->enum('type', ['WINNER', 'FINAL_RANKING', 'TOTAL_GOALS']);
            $table->unsignedSmallInteger('value');
            $table->foreignId('team_id')->nullable()->constrained('teams');
            $table->timestamps();

            $table->index('tournament_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('special_tipp_specs');
    }
};
